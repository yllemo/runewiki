const {chromium}=require('playwright');
const fs=require('fs'),{execFileSync}=require('child_process');
(async()=>{
 const html=execFileSync(process.env.PHP_BINARY || 'php',['-r',`spl_autoload_register(function($c){require 'core/'.$c.'.php';}); $svgNamespace='project'; $assetUrl=fn($p)=>'/assets/'.$p; include 'templates/default/svg-editor.php';`],{encoding:'utf8'});
 const browser=await chromium.launch({channel:'msedge',headless:true});
 try{
 const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('http://wiki.test/',r=>r.fulfill({contentType:'text/html',body:'<meta name="viewport" content="width=device-width,initial-scale=1"><button data-svg-new="project" id="editor-svg-btn">SVG</button><div id="monaco-container" style="height:400px"></div><script>window.WIKI_MEDIA=["project:existing.svg"];</script>'+html}));
 await page.route('http://wiki.test/assets/js/svg-editor.js',r=>r.fulfill({contentType:'text/javascript',body:fs.readFileSync('templates/default/assets/js/svg-editor.js','utf8')}));
 let posts=[];
 await page.route('http://wiki.test/?do=svg**',r=>{
  if(r.request().method()==='POST'){posts.push(r.request().postData());return r.fulfill({json:{id:'project:diagram.svg',url:'/images/project/diagram.svg',revision:'new-revision'}})}
  return r.fulfill({json:{id:'project:existing.svg',source:'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><circle r="20" cx="32" cy="32"/></svg>',revision:'existing-revision'}})
 });
 await page.goto('http://wiki.test/');await page.addStyleTag({content:fs.readFileSync('templates/default/assets/css/style.css','utf8')});
 await page.addScriptTag({url:'https://cdn.jsdelivr.net/npm/monaco-editor@0.52.0/min/vs/loader.js'});
 await page.evaluate(()=>new Promise(resolve=>{require.config({paths:{vs:'https://cdn.jsdelivr.net/npm/monaco-editor@0.52.0/min/vs'}});require(['vs/editor/editor.main'],()=>{window.editor=monaco.editor.create(document.getElementById('monaco-container'),{value:'# Original\n\n',language:'markdown'});editor.setPosition({lineNumber:3,column:1});WikiSvgEditor.bindEditor(editor);resolve()})}));
 for(const width of [1280,390]){
 await page.setViewportSize({width,height:850});await page.click('#editor-svg-btn');
 await page.waitForFunction(()=>document.getElementById('svg-preview').naturalWidth>0);
 const box=await page.locator('#svg-editor').boundingBox();if(box.x<0||box.x+box.width>width)throw Error('Dialog overflow');
 if(await page.locator('#svg-editor').evaluate(n=>n.scrollWidth>n.clientWidth))throw Error('Horizontal overflow');
 for (const sample of ['flow','architecture','timeline','roles','card']) {
 page.once('dialog', d=>d.accept());
 await page.selectOption('#svg-example',sample);
 const source=await page.locator('#svg-source').inputValue();
 execFileSync(process.env.PHP_BINARY || 'php',['-r',"require 'core/SvgDocument.php'; SvgDocument::validate(stream_get_contents(STDIN));"],{input:source});
 await page.waitForFunction(()=>document.getElementById('svg-preview').naturalWidth===840);
 page.removeAllListeners('dialog');
 }
 page.once('dialog',d=>d.accept());await page.click('#svg-close');
 }
 await page.click('#editor-svg-btn');await page.selectOption('#svg-existing','project:existing.svg');
 await page.waitForFunction(()=>document.getElementById('svg-source').value.includes('circle'));
 await page.click('#svg-insert-code');
 if(!(await page.evaluate(()=>editor.getValue())).includes('```svg'))throw Error('Code not inserted');
 await page.evaluate(()=>editor.trigger('test','undo',null));if(await page.evaluate(()=>editor.getValue())!=='# Original\n\n')throw Error('Undo failed');
 await page.click('#editor-svg-btn');await page.click('#svg-insert-file');
 await page.waitForFunction(()=>!document.getElementById('svg-editor').open);
 if(!(await page.evaluate(()=>editor.getValue())).includes('/images/project/diagram.svg'))throw Error('File not inserted');
 if(posts.length!==1||!posts[0].includes('csrf_token'))throw Error('Missing save/CSRF');
 if(errors.length)throw Error(errors.join('\n'));
 console.log('PASS SVG modal desktop/mobile, live preview, samples, existing file, code insertion, undo and media save');
 }finally{await browser.close()}
})().catch(e=>{console.error(e);process.exit(1)});

