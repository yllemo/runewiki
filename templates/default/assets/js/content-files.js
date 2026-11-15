(() => {
 const root=document.getElementById('content-files'); if(!root)return;
 const el=name=>root.querySelector('[data-file-'+name+']');
 let current=null, files=[], folders=[], folder='', busy=false;
 const parent=p=>p.includes('/')?p.slice(0,p.lastIndexOf('/')):'';
 const base=p=>p.split('/').pop();
 const join=(a,b)=>a?a+'/'+b:b;
 const dirty=()=>current&&el('raw').value!==current.raw;
 async function api(operation, extra={}){
  const body=new FormData();body.set('operation',operation);body.set('csrf_token',root.dataset.csrf);
  Object.entries(extra).forEach(([k,v])=>body.set(k,v));
  const response=await fetch('/admin/?do=content_files',{method:'POST',body,credentials:'same-origin'});
  let data;try{data=await response.json();}catch{throw Error('Kunde inte läsa svaret. Kontrollera inloggningen eller uppladdningsgränsen.');}
  if(!response.ok||!data.ok)throw Error(data.error||'Åtgärden misslyckades.');return data;
 }
 function controls(){root.querySelectorAll('button,input,textarea').forEach(n=>n.disabled=busy);el('raw').disabled=busy||!current;['save','move','rename'].forEach(a=>root.querySelector('[data-file-action="'+a+'"]').disabled=busy||!current);}
 async function run(task){if(busy)return;busy=true;controls();el('status').textContent='';try{await task();}catch(e){el('status').textContent=e.message;}finally{busy=false;controls();}}
 function button(label,callback){const b=document.createElement('button');b.type='button';b.textContent=label;b.onclick=()=>{if(!busy)callback();};return b;}
 function navigate(path){folder=path;el('filter').value='';draw();}
 function draw(){
  el('breadcrumb').replaceChildren(button('content',()=>navigate('')));
  let path='';for(const part of folder.split('/').filter(Boolean)){path=join(path,part);const target=path;el('breadcrumb').append(document.createTextNode(' / '),button(part,()=>navigate(target)));}
  el('list').replaceChildren();const query=el('filter').value.toLowerCase();
  if(folder)el('list').append(button('↑ Till överordnad mapp',()=>navigate(parent(folder))));
  folders.filter(p=>parent(p)===folder&&base(p).toLowerCase().includes(query)).forEach(p=>el('list').append(button('📁 '+base(p),()=>navigate(p))));
  files.filter(p=>parent(p)===folder&&base(p).toLowerCase().includes(query)).forEach(path=>{const b=button('▤ '+base(path),()=>{run(async()=>{load(await api('read',{path}));});});b.setAttribute('aria-current',String(current?.path===path));el('list').append(b);});
  if(!el('list').children.length){const p=document.createElement('p');p.textContent=query?'Inga träffar':'Mappen är tom';el('list').append(p);}
 }
 function load(data){current=data;el('raw').value=data.raw;el('name').textContent=data.path;draw();}
 async function refresh(){const data=await api('list');files=data.files;folders=data.folders;draw();}
 el('filter').oninput=draw;
 el('raw').oninput=()=>{el('name').textContent=current.path+(dirty()?' •':'');};
 async function upload(selected){const list=Array.from(selected);if(!list.length||busy)return;const destination=folder;
  run(async()=>{let count=0;const errors=[];
   for(let i=0;i<list.length;i++){const file=list[i];el('status').textContent='Laddar upp '+(i+1)+' / '+list.length+'…';
    try{if(!file.name.endsWith('.md')||file.size>524288)throw Error('Endast .md, högst 512 KB');await api('upload',{path:join(destination,file.name),file});count++;}catch(e){errors.push(file.name+': '+e.message);}
   }
   await refresh();el('status').textContent=count+' filer uppladdade'+(errors.length?'. '+errors.join(' · '):'.');el('upload').value='';
  });
 }
 el('upload').onchange=()=>upload(el('upload').files);
 let dragDepth=0;
 root.addEventListener('dragenter',e=>{if(Array.from(e.dataTransfer?.types||[]).includes('Files')){e.preventDefault();dragDepth++;root.classList.add('is-file-drop');}});
 root.addEventListener('dragover',e=>{if(Array.from(e.dataTransfer?.types||[]).includes('Files')){e.preventDefault();e.dataTransfer.dropEffect=busy?'none':'copy';}});
 root.addEventListener('dragleave',()=>{if(--dragDepth<=0){dragDepth=0;root.classList.remove('is-file-drop');}});
 root.addEventListener('drop',e=>{e.preventDefault();dragDepth=0;root.classList.remove('is-file-drop');upload(e.dataTransfer.files);});
 root.addEventListener('click',event=>{const action=event.target.closest('[data-file-action]')?.dataset.fileAction;if(!action||busy)return;
  if(action==='upload'){el('upload').click();return;}
  let value;
  if(action==='mkdir'||action==='create'){value=prompt(action==='mkdir'?'Mappnamn:':'Filnamn (.md):',action==='create'?'ny-sida.md':'');if(!value?.trim())return;value=join(folder,value.trim());}
  if(action==='rename'){value=prompt('Nytt filnamn:',base(current.path));if(!value?.trim())return;if(value.includes('/')||value.includes('\\')){el('status').textContent='Ange bara filnamnet. Använd Flytta för att byta mapp.';return;}value=join(parent(current.path),value.trim());}
  if(action==='move'){value=prompt('Målmapp under /content (tomt för roten):',parent(current.path));if(value===null)return;value=join(value.trim().replace(/\/$/,''),base(current.path));}
  run(async()=>{
   if(action==='refresh'){await refresh();return;}
   if(action==='mkdir'){await api('mkdir',{path:value});await refresh();navigate(value);el('status').textContent='Mappen skapad.';return;}
   if(action==='create'){load(await api('create',{path:value}));el('status').textContent='Filen skapad.';}
   if(action==='save'){load(await api('save',{path:current.path,revision:current.revision,raw:el('raw').value}));el('status').textContent='Sparat.';}
   if(action==='move'||action==='rename'){load(await api('move',{path:current.path,revision:current.revision,destination:value}));folder=parent(current.path);el('status').textContent=action==='move'?'Filen flyttad.':'Filen omdöpt.';}
   await refresh();
  });
 });
 run(refresh);
})();
