import { runCLI } from '@wp-playground/cli';
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const repo=path.resolve(import.meta.dirname,'..');
const server=await runCLI({command:'server',wp:process.env.QA_WP||'6.8.3',php:process.env.QA_PHP||'8.2',port:9411,workers:1,login:false,verbosity:'normal',mount:[{hostPath:repo+'/giuliomax-product-passport',vfsPath:'/wordpress/wp-content/plugins/giuliomax-product-passport'},{hostPath:repo+'/tests/harness.php',vfsPath:'/wordpress/gmpp-harness.php'}]});
try {
 const response=await server.playground.run({scriptPath:'/wordpress/gmpp-harness.php'});const raw=response.text; console.log('PHP_ERRORS',response.errors);
 fs.mkdirSync(repo+'/test-results',{recursive:true});fs.writeFileSync(repo+'/test-results/backend-raw.txt',raw);
 let result;try{result=JSON.parse(raw);}catch{console.log(raw.slice(-12000));throw new Error('Harness response is not JSON');}
 fs.writeFileSync(repo+'/test-results/backend.json',JSON.stringify(result,null,2));
 for(const t of result.tests)console.log((t.ok?'PASS ':'FAIL ')+t.name+(t.error?' — '+t.error:''));
 console.log('FIXTURE',JSON.stringify(result.fixture));
 const qr=await fetch(server.serverUrl+'/qa-qr.png');fs.writeFileSync(repo+'/test-results/qr.png',Buffer.from(await qr.arrayBuffer()));
 for(const name of ['qa-long-qr.png','qa-label.svg']){const r=await fetch(server.serverUrl+'/'+name);fs.writeFileSync(repo+'/test-results/'+name,Buffer.from(await r.arrayBuffer()));}
 const pdf=await fetch(server.serverUrl+'/qa-sheet.pdf');fs.writeFileSync(repo+'/test-results/sheet.pdf',Buffer.from(await pdf.arrayBuffer()));
 if(process.env.QA_PERF==='1'){
  await server.playground.writeFile('/wordpress/gmpp-perf.php',fs.readFileSync(repo+'/tests/performance.php','utf8'));
  const init=await server.playground.run({scriptPath:'/wordpress/gmpp-perf.php'});fs.writeFileSync(repo+'/test-results/perf-init.txt',init.text+'\nERRORS: '+init.errors);const info=JSON.parse(init.text);console.log('PERF_INIT',JSON.stringify(info));const start=performance.now();let job;
  for(let n=0;n<220;n++){
    const response=await server.playground.run({code:`<?php require '/wordpress/wp-load.php';wp_set_current_user(1);Giuliomax\\Passport\\Jobs::tick();$j=Giuliomax\\Passport\\Store::one('jobs',${info.job_id});echo json_encode(['state'=>$j['state'],'cursor'=>$j['cursor'],'errors'=>count(array_filter(Giuliomax\\Passport\\Store::decode($j['report']),fn($x)=>!$x['ok']))]);`});
    job=JSON.parse(response.text);if(n%20===0)console.log('PERF_PROGRESS',job.cursor);if(job.state==='done')break;
  }
  const import_seconds=(performance.now()-start)/1000;
  const query=await server.playground.run({code:`<?php require '/wordpress/wp-load.php';wp_set_current_user(1);rest_get_server();$results=[];foreach(['list'=>'/gmpp/v1/passports','search'=>'/gmpp/v1/passports?search=PERF-09999'] as $key=>$route){$samples=[];for($i=0;$i<7;$i++){$r=new WP_REST_Request('GET',strtok($route,'?'));if($key==='search')$r->set_param('search','PERF-09999');$t=microtime(true);$response=rest_do_request($r);$samples[]=(microtime(true)-$t)*1000;}$results[$key]=['ms'=>$samples,'status'=>$response->get_status(),'count'=>$response->get_data()['total']??null];}echo json_encode($results);`});
  const performanceResult={...info,import_seconds,job,queries:JSON.parse(query.text),platform:process.platform,node:process.version};fs.writeFileSync(repo+'/test-results/performance.json',JSON.stringify(performanceResult,null,2));console.log('PERFORMANCE',JSON.stringify(performanceResult));
 }
 if(process.env.QA_BROWSER==='1'){
  const {chromium}=require('@playwright/test');const {AxeBuilder}=await import('@axe-core/playwright');
  const chromiumModule=require('@sparticuz/chromium');const chromiumBinary=chromiumModule.default||chromiumModule;const browser=await chromium.launch({headless:true,executablePath:process.env.QA_CHROME || await chromiumBinary.executablePath(),args:chromiumBinary.args});
  const context=await browser.newContext({viewport:{width:1440,height:1000}});const page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));page.on('console',m=>{if(m.type()==='error')console.log('CONSOLE',m.text());});
  await page.goto(server.serverUrl+'/wp-login.php');await page.locator('#user_login').fill('admin');await page.locator('#user_pass').fill('password');await page.locator('#wp-submit').click();
  await page.goto(server.serverUrl+'/wp-admin/admin.php?page=gmpp');await page.waitForTimeout(1200);fs.writeFileSync(repo+'/test-results/browser-debug.html',await page.content());await page.getByRole('heading',{name:'Un prodotto. Tutte le sue informazioni.'}).waitFor();
  await page.screenshot({path:repo+'/test-results/admin-desktop.png',fullPage:true});
  const adminAxe=await new AxeBuilder({page}).include('#gmpp-app').withTags(['wcag2a','wcag2aa','wcag21aa','wcag22aa']).analyze();
  await page.goto(server.serverUrl+'/wp-admin/admin.php?page=gmpp-passports&edit='+result.fixture.id);await page.locator('#editor-form').waitFor();
  await page.locator('#f-name').fill('Browser edited draft');await page.getByRole('button',{name:'Salva bozza',exact:true}).click();await page.getByRole('status').filter({hasText:'Bozza salvata. La versione pubblica è invariata.'}).waitFor();
  await page.getByRole('button',{name:'Pubblica revisione',exact:true}).click();await page.getByRole('button',{name:'Pubblica e mostra QR'}).click();await page.getByRole('link',{name:'Apri la scheda pubblica'}).waitFor();
  const editorAxe=await new AxeBuilder({page}).include('#gmpp-app').withTags(['wcag2a','wcag2aa','wcag21aa','wcag22aa']).analyze();await page.screenshot({path:repo+'/test-results/editor-desktop.png',fullPage:true});await page.setViewportSize({width:390,height:844});await page.screenshot({path:repo+'/test-results/admin-mobile.png',fullPage:true});const adminOverflow=await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth);await page.setViewportSize({width:1440,height:1000});
  const anon=await browser.newContext({viewport:{width:390,height:844}});const publicPage=await anon.newPage();await publicPage.goto(result.fixture.url);await publicPage.getByRole('heading',{name:'Browser edited draft',exact:true}).waitFor();
  const overflow=await publicPage.evaluate(()=>document.documentElement.scrollWidth>innerWidth);await publicPage.screenshot({path:repo+'/test-results/public-mobile.png',fullPage:true});
  const publicAxe=await new AxeBuilder({page:publicPage}).withTags(['wcag2a','wcag2aa','wcag21aa','wcag22aa']).analyze();
  const denied=await publicPage.request.get(server.serverUrl+'/?gmpp_document='+result.fixture.private_version);
  const qrResponse=await publicPage.request.get(result.fixture.url+'&format=png');if(!Buffer.from(await qrResponse.body()).subarray(0,8).equals(Buffer.from([137,80,78,71,13,10,26,10])))throw new Error('Invalid HTTP PNG signature');await page.evaluate(async()=>{await fetch(GMPP.api+'settings',{method:'PUT',headers:{'X-WP-Nonce':GMPP.nonce,'Content-Type':'application/json'},body:JSON.stringify({ui_language:'en'})});});await page.goto(server.serverUrl+'/wp-admin/admin.php?page=gmpp');await page.getByRole('heading',{name:'One product. All its information.',exact:true}).waitFor();const englishPanel=true;
  console.log('BROWSER',JSON.stringify({errors,overflow,privateStatus:denied.status(),qrStatus:qrResponse.status(),englishPanel,adminOverflow,editorViolations:editorAxe.violations.map(v=>({id:v.id,impact:v.impact,nodes:v.nodes.map(n=>n.target)})),adminViolations:adminAxe.violations.map(v=>({id:v.id,impact:v.impact,nodes:v.nodes.map(n=>n.target)})),publicViolations:publicAxe.violations.map(v=>({id:v.id,impact:v.impact,nodes:v.nodes.map(n=>n.target)}))}));
  fs.writeFileSync(repo+'/test-results/browser.json',JSON.stringify({errors,overflow,privateStatus:denied.status(),adminAxe,editorAxe,adminOverflow,englishPanel,publicAxe},null,2));await browser.close();if(errors.length||overflow||adminOverflow||denied.status()!==404||qrResponse.status()!==200||adminAxe.violations.length||editorAxe.violations.length||publicAxe.violations.length||!englishPanel)throw new Error('Browser acceptance checks failed.');
 }
 if(result.tests.some(t=>!t.ok))process.exitCode=1;
}finally{await server[Symbol.asyncDispose]();}
