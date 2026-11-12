(()=>{
 const search=document.querySelector('#help-search');if(!search)return;
 const entries=[...document.querySelectorAll('.help-entry')],fold=s=>s.toLocaleLowerCase('hu').normalize('NFD').replace(/[\u0300-\u036f]/g,'');
 const filter=()=>{const q=fold(search.value.trim());let count=0;for(const e of entries){e.hidden=!!q&&!fold(e.textContent).includes(q);if(!e.hidden)count++;if(q)e.open=!e.hidden;else e.open=false;}document.querySelectorAll('.help-section').forEach(s=>s.hidden=![...s.querySelectorAll('.help-entry')].some(e=>!e.hidden));document.querySelector('#help-count').textContent=q?count+' találat':'';document.querySelector('#help-empty').hidden=count>0;};
 search.addEventListener('input',filter);document.querySelectorAll('.help-topics a').forEach(a=>a.addEventListener('click',()=>{search.value='';filter();}));filter();
})();
