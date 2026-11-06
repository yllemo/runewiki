// Run from repository root: node tests/editor-tools.cjs (requires Playwright, Edge and CDN access).
const { chromium } = require('playwright');
const fs=require('fs');
(async()=>{
 const browser=await chromium.launch({channel:'msedge',headless:true});
 try {
 const page=await browser.newPage();page.on('console',m=>{if(m.type()==='error')console.log('BROWSER: '+m.text())});
 let html=fs.readFileSync('templates/default/edit.php','utf8').replace(/<\?php[\s\S]*?\?>|<\?=[\s\S]*?\?>/g,'').replace(/<script\b[^>]*>[\s\S]*?<\/script>/g,'');
 await page.route('http://wiki.test/',r=>r.fulfill({contentType:'text/html',body:'<meta name="viewport" content="width=device-width,initial-scale=1">'+html}));
 await page.goto('http://wiki.test/');
 await page.addStyleTag({content:fs.readFileSync('templates/default/assets/css/style.css','utf8')});
 await page.addScriptTag({url:'https://cdn.jsdelivr.net/npm/monaco-editor@0.52.0/min/vs/loader.js'});
 await page.addScriptTag({content:fs.readFileSync('templates/default/assets/js/editor-tools.js','utf8')});
 await page.evaluate(()=>new Promise(resolve=>{require.config({paths:{vs:'https://cdn.jsdelivr.net/npm/monaco-editor@0.52.0/min/vs'}});require(['vs/editor/editor.main'],()=>{window.testEditor=monaco.editor.create(document.getElementById('monaco-container'),{value:'Existing text',language:'markdown',automaticLayout:true});WikiEditorTools(testEditor);resolve()})}));
 for(const width of [1280,390]){
 await page.setViewportSize({width,height:850});
 await page.click('#editor-fullscreen-btn');
 const box=await page.locator('#edit-form').boundingBox();
 if(box.x!==0||box.y!==0||box.width!==width||box.height!==850)throw Error('Fullscreen bounds');
 await page.keyboard.press('Escape');
 if(await page.locator('#edit-form').evaluate(n=>n.classList.contains('is-fullscreen')))throw Error('Escape fullscreen');
 }
 await page.click('#editor-fullscreen-btn');
 await page.click('#editor-import-btn');
 async function load(name,buffer,expected){
  await page.locator('#editor-import-file').setInputFiles({name,mimeType:'application/octet-stream',buffer});
  await page.waitForFunction(()=>!document.getElementById('editor-import-preview').disabled,{},{timeout:60000});
  const status=await page.locator('#editor-import-status').textContent();
  const preview=await page.locator('#editor-import-preview').inputValue();
  if(!preview.includes(expected))throw Error(name+': '+status+' / '+preview);
  if(await page.evaluate(()=>testEditor.getValue())!=='Existing text')throw Error('Premature change');
  console.log('PASS '+name);
 }
 await load('example.md',Buffer.from('# Imported\n\nText'),'# Imported');
 await load('example.html',Buffer.from('<h1>HTML title</h1><p>Hello <strong>world</strong></p><script>alert(1)</script><table><tr><td>A</td><td>B</td></tr><tr><td>C</td><td>D</td></tr></table>'),'# HTML title');
 if((await page.locator('#editor-import-preview').inputValue()).includes('alert'))throw Error('Unsafe HTML');
 await load('example.docx',Buffer.from('UEsDBBQAAAAAAAd6Zl10JJxTPgEAAD4BAAATAAAAW0NvbnRlbnRfVHlwZXNdLnhtbDxUeXBlcyB4bWxucz0iaHR0cDovL3NjaGVtYXMub3BlbnhtbGZvcm1hdHMub3JnL3BhY2thZ2UvMjAwNi9jb250ZW50LXR5cGVzIj48RGVmYXVsdCBFeHRlbnNpb249InJlbHMiIENvbnRlbnRUeXBlPSJhcHBsaWNhdGlvbi92bmQub3BlbnhtbGZvcm1hdHMtcGFja2FnZS5yZWxhdGlvbnNoaXBzK3htbCIvPjxPdmVycmlkZSBQYXJ0TmFtZT0iL3dvcmQvZG9jdW1lbnQueG1sIiBDb250ZW50VHlwZT0iYXBwbGljYXRpb24vdm5kLm9wZW54bWxmb3JtYXRzLW9mZmljZWRvY3VtZW50LndvcmRwcm9jZXNzaW5nbWwuZG9jdW1lbnQubWFpbit4bWwiLz48L1R5cGVzPlBLAwQUAAAAAAAHemZdYXsvQ/IAAADyAAAACwAAAF9yZWxzLy5yZWxzPFJlbGF0aW9uc2hpcHMgeG1sbnM9Imh0dHA6Ly9zY2hlbWFzLm9wZW54bWxmb3JtYXRzLm9yZy9wYWNrYWdlLzIwMDYvcmVsYXRpb25zaGlwcyI+PFJlbGF0aW9uc2hpcCBJZD0icklkMSIgVHlwZT0iaHR0cDovL3NjaGVtYXMub3BlbnhtbGZvcm1hdHMub3JnL29mZmljZURvY3VtZW50LzIwMDYvcmVsYXRpb25zaGlwcy9vZmZpY2VEb2N1bWVudCIgVGFyZ2V0PSJ3b3JkL2RvY3VtZW50LnhtbCIvPjwvUmVsYXRpb25zaGlwcz5QSwMEFAAAAAAAB3pmXc4Aw3iiAAAAogAAABEAAAB3b3JkL2RvY3VtZW50LnhtbDx3OmRvY3VtZW50IHhtbG5zOnc9Imh0dHA6Ly9zY2hlbWFzLm9wZW54bWxmb3JtYXRzLm9yZy93b3JkcHJvY2Vzc2luZ21sLzIwMDYvbWFpbiI+PHc6Ym9keT48dzpwPjx3OnI+PHc6dD5Xb3JkIGltcG9ydCB0ZXN0PC93OnQ+PC93OnI+PC93OnA+PC93OmJvZHk+PC93OmRvY3VtZW50PlBLAQIUABQAAAAAAAd6Zl10JJxTPgEAAD4BAAATAAAAAAAAAAAAAACAAQAAAABbQ29udGVudF9UeXBlc10ueG1sUEsBAhQAFAAAAAAAB3pmXWF7L0PyAAAA8gAAAAsAAAAAAAAAAAAAAIABbwEAAF9yZWxzLy5yZWxzUEsBAhQAFAAAAAAAB3pmXc4Aw3iiAAAAogAAABEAAAAAAAAAAAAAAIABigIAAHdvcmQvZG9jdW1lbnQueG1sUEsFBgAAAAADAAMAuQAAAFsDAAAAAA==','base64'),'Word import test');
 function pdf(){const stream='BT /F1 12 Tf 72 720 Td (PDF import test) Tj ET';const objects=['<< /Type /Catalog /Pages 2 0 R >>','<< /Type /Pages /Kids [3 0 R] /Count 1 >>','<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>','<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',`<< /Length ${stream.length} >>\nstream\n${stream}\nendstream`];let s='%PDF-1.4\n',offsets=[0];objects.forEach((o,i)=>{offsets.push(s.length);s+=`${i+1} 0 obj\n${o}\nendobj\n`});const x=s.length;s+='xref\n0 6\n0000000000 65535 f \n'+offsets.slice(1).map(o=>String(o).padStart(10,'0')+' 00000 n \n').join('')+`trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n${x}\n%%EOF`;return Buffer.from(s);}
 await load('example.pdf',pdf(),'PDF import test');
 await page.click('#editor-import-insert');
 if(!(await page.evaluate(()=>testEditor.getValue())).includes('PDF import test'))throw Error('Insert failed');
 await page.evaluate(()=>testEditor.trigger('test','undo',null));
 if(await page.evaluate(()=>testEditor.getValue())!=='Existing text')throw Error('Undo failed');
 await page.click('#editor-import-btn');
 await load('replace.md',Buffer.from('# Replacement'),'# Replacement');
 page.once('dialog',d=>d.accept());
 await page.click('#editor-import-replace');
 if(await page.evaluate(()=>testEditor.getValue())!=='# Replacement')throw Error('Replace failed');
 await page.evaluate(()=>testEditor.trigger('test','undo',null));
 if(await page.evaluate(()=>testEditor.getValue())!=='Existing text')throw Error('Replace undo failed');
 await page.click('#editor-import-btn');
 await page.locator('#editor-import-file').setInputFiles({name:'broken.docx',mimeType:'application/octet-stream',buffer:Buffer.from('not a zip')});
 await page.waitForFunction(()=>document.getElementById('editor-import-status').textContent.startsWith('Kunde inte importera'));
 if(!await page.locator('#editor-import-insert').isDisabled()||await page.evaluate(()=>testEditor.getValue())!=='Existing text')throw Error('Failed conversion changed editor');
 await page.click('#editor-import-close');
 console.log('PASS fullscreen desktop/mobile, preview isolation, insertion and undo');
 }finally{await browser.close()}
})().catch(e=>{console.error(e);process.exit(1)});



