const {chromium}=require('playwright');const fs=require('fs');
(async()=>{const browser=await chromium.launch({channel:'msedge',headless:true});try{
 const page=await browser.newPage();let uploads=[];
 await page.route('http://wiki.test/admin/?do=content_files',async r=>{const body=r.request().postData()||'';if(body.includes('\r\n\r\nupload\r\n')){uploads.push(body);await r.fulfill({json:{ok:true}});}else await r.fulfill({json:{ok:true,files:[],folders:['docs']}});});
 await page.goto('http://wiki.test/admin/?do=content_files');
 let html=fs.readFileSync('admin/index.php','utf8').split('<div id="content-files"')[1].split('    <script src=')[0];html='<div id="content-files"'+html;html=html.replace(/<\?php[\s\S]*?\?>|<\?=[\s\S]*?\?>/g,'token');
 await page.setContent(html);await page.addStyleTag({content:fs.readFileSync('templates/default/assets/css/style.css','utf8')});await page.addScriptTag({content:fs.readFileSync('templates/default/assets/js/content-files.js','utf8')});
 await page.getByRole('button',{name:'📁 docs'}).click();
 await page.locator('[data-file-upload]').setInputFiles([{name:'a.md',mimeType:'text/markdown',buffer:Buffer.from('# A')},{name:'b.md',mimeType:'text/markdown',buffer:Buffer.from('# B')}]);
 await page.waitForFunction(()=>document.querySelector('[data-file-status]').textContent==='2 filer uppladdade.');
 if(uploads.length!==2||!uploads[0].includes('docs/a.md')||!uploads[1].includes('docs/b.md'))throw Error('Wrong multi-upload paths');
 await page.evaluate(()=>{const dt=new DataTransfer();dt.items.add(new File(['# C'],'c.md',{type:'text/markdown'}));document.getElementById('content-files').dispatchEvent(new DragEvent('drop',{bubbles:true,dataTransfer:dt}));});
 await page.waitForFunction(()=>document.querySelector('[data-file-status]').textContent==='1 filer uppladdade.');
 if(uploads.length!==3||!uploads[2].includes('docs/c.md'))throw Error('Drop failed');
 console.log('PASS folder navigation, multi-file upload and drag-and-drop destination');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1)});
