const fs=require('fs'),{PNG}=require('pngjs'),z=require('@zxing/library'),sharp=require('sharp');
(async()=>{
 await sharp('test-results/qa-label.svg').resize(331,331).png().toFile('test-results/label-28mm-300dpi.png');
 const results=[];
 for(const file of ['qr.png','qa-long-qr.png','label-28mm-300dpi.png']){
  const png=PNG.sync.read(fs.readFileSync('test-results/'+file));const pixels=new Int32Array(png.width*png.height);
  for(let i=0;i<pixels.length;i++)pixels[i]=(png.data[i*4]<<16)|(png.data[i*4+1]<<8)|png.data[i*4+2];
  const bitmap=new z.BinaryBitmap(new z.HybridBinarizer(new z.RGBLuminanceSource(pixels,png.width,png.height)));
  let text,pure=false;try{text=new z.QRCodeReader().decode(bitmap).getText();}catch{pure=true;text=new z.QRCodeReader().decode(bitmap,new Map([[z.DecodeHintType.PURE_BARCODE,true]])).getText();}
  results.push({file,width:png.width,height:png.height,decodedBytes:Buffer.byteLength(text),pure});
 }
 fs.writeFileSync('test-results/qr-decode.json',JSON.stringify(results,null,2));console.log(JSON.stringify(results,null,2));
})();
