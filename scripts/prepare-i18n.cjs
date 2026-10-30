const fs=require('fs'),acorn=require('acorn');
const file='giuliomax-product-passport/assets/admin.js';let source=fs.readFileSync(file,'utf8');
const tree=acorn.parse(source,{ecmaVersion:'latest'});const edits=[],messages=new Set();
function visit(node,parent){if(!node||typeof node!=='object')return;if(node.type==='TemplateLiteral'&&parent?.type!=='TaggedTemplateExpression'){edits.push(node.start);for(const q of node.quasis){const text=q.value.cooked;for(const m of text.matchAll(/(?:^|>)([^<>]+)(?=<|$)/g)){let value=m[1].trim();if(/[a-zA-Zà-ù]/.test(value)&&!/[={}]/.test(value))messages.add(value);}}}if(node.type==='Literal'&&typeof node.value==='string'&&/[à-ù ]/.test(node.value)&&node.value.length>3)messages.add(node.value);for(const [k,v]of Object.entries(node))if(k!=='start'&&k!=='end'){if(Array.isArray(v))v.forEach(x=>visit(x,node));else if(v&&typeof v==='object')visit(v,node);}}
visit(tree,null);for(const at of edits.sort((a,b)=>b-a))source=source.slice(0,at)+'html'+source.slice(at);
fs.writeFileSync(file,source);fs.writeFileSync('scripts/ui-strings.json',JSON.stringify([...messages].sort(),null,2));
