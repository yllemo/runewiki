(() => {
  const root=document.getElementById('colab'); if(!root) return;
  const el=id=>document.getElementById('colab-'+id), form=el('form');
  let comments=JSON.parse(el('data').textContent), quote='', parent='', busy=false;
  const blocks=[];
  const normalize=text=>text.replace(/\s+/g,' ').trim();
  function node(tag,text,className){const n=document.createElement(tag);n.textContent=text;if(className)n.className=className;return n;}
  function button(text,fn){const b=node('button',text,'gbg-btn gbg-btn-outline');b.type='button';b.onclick=fn;return b;}
  function target(text='',reply=''){
    quote=text;parent=reply;
    el('context').textContent=text?'Stycke: '+text:'Hela artikeln';
    el('clear').hidden=!text;
    el('reply').hidden=!reply;el('cancel-reply').hidden=!reply;
    el('reply').textContent=reply?'Svar i vald tråd':'';
    render();
  }
  function render(){
    el('count').textContent='('+comments.length+')';
    el('list').replaceChildren();
    const filter=el('filter').value;
    const threads=comments.filter(c=>!c.parent && (!quote || c.quote===quote) && (filter==='all' || (filter==='resolved')===c.resolved));
    if(!threads.length) el('list').append(node('p','Inga kommentarer att visa.'));
    function entry(c,reply=false){
      const card=node('div','', 'gbg-colab-comment'+(reply?' gbg-colab-reply':'')+(c.resolved?' is-resolved':''));
      card.append(node('strong',c.author+(c.authenticated?'':' (gäst)')));
      card.append(node('small',new Date(c.created).toLocaleString('sv-SE')+(c.private?' · Endast inloggade':'')+(c.resolved?' · Löst':'')));
      if(!reply && c.quote){
        card.append(node('blockquote',c.quote));
        const block=blocks.find(b=>b.quote===c.quote);
        if(block) card.append(button('Visa stycke',()=>{block.node.scrollIntoView({behavior:'smooth',block:'center'});block.button.focus({preventScroll:true});}));
        else card.append(node('small','Stycket har ändrats eller finns inte längre.'));
      }
      card.append(node('p',c.text));
      const actions=node('div','','gbg-colab-toolbar');
      if(!reply) actions.append(button('Svara',()=>{target(c.quote,c.id);form.elements.text.focus();}));
      if(root.dataset.moderate==='1'){
        if(!reply)actions.append(button(c.resolved?'Öppna igen':'Markera löst',()=>change('resolve',{id:c.id})));
        actions.append(button('Ta bort',()=>{if(confirm(reply?'Ta bort svaret?':'Ta bort tråden och dess svar?'))change('delete',{id:c.id});}));
      }
      card.append(actions); return card;
    }
    for(const c of threads){const card=entry(c);for(const r of comments.filter(r=>r.parent===c.id))card.append(entry(r,true));el('list').append(card);}
    for(const b of blocks){const count=comments.filter(c=>!c.parent && c.quote===b.quote).length;b.button.textContent=count?'💬 '+count:'💬';b.button.setAttribute('aria-label','Kommentera stycket'+(count?' ('+count+' trådar)':''));b.node.classList.toggle('has-comments',count>0);}
  }
  async function response(res){let data;try{data=await res.json();}catch(e){throw new Error('Sessionen eller åtkomsten har ändrats. Ladda om sidan.');}if(!res.ok||data.error)throw new Error(data.error||'Kunde inte hämta kommentarer.');return data.comments;}
  async function change(action,fields={}){
    if(busy)return false;busy=true;form.querySelector('[type="submit"]').disabled=true;
    try{const data=new FormData(form);data.set('action',action);for(const [k,v]of Object.entries(fields))data.set(k,v);
      comments=await response(await fetch(root.dataset.url,{method:'POST',body:data,credentials:'same-origin'}));
      el('status').textContent='Sparat.';render();return true;
    }catch(e){el('status').textContent=e.message;return false;}finally{busy=false;form.querySelector('[type="submit"]').disabled=false;}
  }
  async function refresh(){if(busy)return;try{comments=await response(await fetch(root.dataset.url+'&op=list',{credentials:'same-origin',cache:'no-store'}));render();}catch(e){el('status').textContent=e.message;}}
  // Capture text before adding controls or Mermaid transforms; do not alter article markup.
  for(const block of Array.from(el('article').children)){
    if(!block.matches('p,h1,h2,h3,h4,h5,h6,ul,ol,blockquote,pre,table,div'))continue;
    const text=normalize(block.textContent).slice(0,1000);if(!text)continue;
    const b=button('💬',()=>{target(text);form.scrollIntoView({behavior:'smooth',block:'nearest'});form.elements.text.focus({preventScroll:true});});
    b.className='gbg-colab-trigger';b.title='Kommentera stycket';
    block.classList.add('gbg-colab-block');block.insertAdjacentElement('beforebegin',b);
    blocks.push({quote:text,node:block,button:b});
  }
  form.onsubmit=async e=>{e.preventDefault();if(await change('add',{quote,parent})){form.elements.text.value='';target(quote);}};
  el('general').onclick=()=>{target();form.elements.text.focus();};
  el('clear').onclick=()=>target();el('cancel-reply').onclick=()=>target(quote);
  el('filter').onchange=render;el('refresh').onclick=refresh;
  setInterval(()=>{if(!document.hidden)refresh();},15000);
  render();
})();
