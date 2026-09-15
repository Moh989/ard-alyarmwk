// Run before the first stylesheet: the visitor's preference is applied before first paint.
(()=>{
 'use strict';
 const key='ard-theme',root=document.documentElement,system=matchMedia('(prefers-color-scheme: dark)');
 const valid=value=>value==='dark'||value==='light'?value:null;
 let preference=null;
 try{preference=valid(localStorage.getItem(key))}catch{}
 const selected=()=>preference||(system.matches?'dark':'light');
 function render(theme){
  root.dataset.theme=theme;
  document.querySelector('meta[name="theme-color"]')?.setAttribute('content',theme==='dark'?'#121827':'#f6f6f2');
  document.querySelectorAll('[data-theme-toggle]').forEach(button=>{
   const label=theme==='dark'?button.dataset.labelLight:button.dataset.labelDark;
   button.setAttribute('aria-label',label);button.title=label;
  });
  document.querySelectorAll('iframe').forEach(frame=>frame.contentWindow?.postMessage({type:'ard-theme',theme},location.origin));
 }
 // Same-origin embedded viewers inherit the current page, including when storage is unavailable.
 function pageTheme(){let theme=selected();try{if(parent!==window)theme=valid(parent.document.documentElement.dataset.theme)||theme}catch{}return theme;}
 render(pageTheme());
 document.addEventListener('DOMContentLoaded',()=>{
  render(root.dataset.theme);
  document.querySelectorAll('[data-theme-toggle]').forEach(button=>{
   button.hidden=false;
   button.addEventListener('click',()=>{
    preference=root.dataset.theme==='dark'?'light':'dark';
    try{localStorage.setItem(key,preference)}catch{}
    render(preference);
   });
  });
 });
 system.addEventListener('change',()=>{if(!preference)render(selected())});
 window.addEventListener('storage',event=>{if(event.key===key||event.key===null){preference=valid(event.newValue);render(selected())}});
 window.addEventListener('message',event=>{if(parent!==window&&event.source===parent&&event.origin===location.origin&&event.data?.type==='ard-theme'&&valid(event.data.theme))render(event.data.theme)});
 window.addEventListener('pageshow',()=>{try{preference=valid(localStorage.getItem(key))}catch{}render(pageTheme())});
})();
