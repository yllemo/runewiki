const {chromium}=require('playwright');
const fs=require('fs');
(async()=>{
 const browser=await chromium.launch({channel:'msedge',headless:true});
 try {
  const page=await browser.newPage();
  await page.goto('about:blank');
  await page.setContent('<button id="opener">Open</button><div id="original"></div>');
  await page.addStyleTag({content:fs.readFileSync('templates/default/assets/css/viewer.css','utf8')});
  await page.addScriptTag({url:'https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.min.js'});
  await page.addScriptTag({content:fs.readFileSync('templates/default/assets/js/viewer.js','utf8')});
  await page.evaluate(async()=>{
   window.source='flowchart LR\nA[Start]-->B[End]\nclick A "https://example.com/" "Open" _blank';
   const result=await renderWikiMermaid(source,'original-render');
   document.getElementById('original').innerHTML=result.svg;
   window.saved=[];window.viewer=createWikiViewer();
   window.openViewer=()=>viewer.open(document.querySelector('#original svg'),document.getElementById('opener'),{source,securityLevel:'strict',onSave:async value=>saved.push(value)});
   openViewer();
  });
  await page.waitForSelector('.mmd-canvas a[href="https://example.com/"]');
  await page.route('https://example.com/**',r=>r.fulfill({body:'Link works'}));
  const popupPromise=page.waitForEvent('popup');
  await page.locator('.mmd-canvas a').click();
  const popup=await popupPromise;await popup.close();
  await page.click('[data-action="code"]');
  await page.fill('.mmd-code-panel textarea','flowchart LR\nA-->C');
  await page.click('[data-action="save-code"]');
  await page.waitForFunction(()=>saved.length===1&&document.querySelector('.mmd-code-panel').hidden);
  await page.click('[data-action="code"]');
  await page.fill('.mmd-code-panel textarea','not a diagram');
  await page.click('[data-action="save-code"]');
  await page.waitForFunction(()=>document.querySelector('.mmd-code-panel [role="status"]').textContent!=='Kontrollerar och sparar…');
  if(await page.evaluate(()=>saved.length)!==1)throw Error('Invalid diagram saved');
  await page.fill('.mmd-code-panel textarea','flowchart LR\nA-->D');
  await page.click('[data-action="close"]');
  await page.waitForFunction(()=>!document.querySelector('.mmd-viewer').open&&saved.length===2);
  await page.evaluate(()=>openViewer());await page.click('[data-action="code"]');
  await page.fill('.mmd-code-panel textarea','flowchart LR\nA-->E');
  await page.click('[data-action="cancel-code"]');await page.click('[data-action="close"]');
  if(await page.evaluate(()=>saved.length)!==2)throw Error('Cancel saved changes');
  console.log('PASS Mermaid links, source editing, save, invalid syntax, save on close and cancel');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
