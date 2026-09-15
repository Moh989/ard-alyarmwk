document.body.classList.add('js');
const menuButton=document.querySelector('.menu-toggle');
const nav=document.querySelector('.main-nav');
const sectorMenu=document.querySelector('.nav-sectors');
const sectorToggle=sectorMenu?.querySelector('summary');
function toggleMenu(open){menuButton?.setAttribute('aria-expanded',String(open));nav?.classList.toggle('open',open);document.body.classList.toggle('menu-open',open);if(!open&&sectorMenu)sectorMenu.open=false}
menuButton?.addEventListener('click',()=>toggleMenu(menuButton.getAttribute('aria-expanded')!=='true'));
document.addEventListener('keydown',e=>{if(e.key!=='Escape')return;if(sectorMenu?.open){sectorMenu.open=false;sectorToggle.focus();return}if(menuButton?.getAttribute('aria-expanded')==='true'){toggleMenu(false);menuButton.focus()}});
document.addEventListener('pointerdown',e=>{if(sectorMenu?.open&&!sectorMenu.contains(e.target))sectorMenu.open=false});
sectorMenu?.addEventListener('focusout',e=>{if(e.relatedTarget&&!sectorMenu.contains(e.relatedTarget))sectorMenu.open=false});
nav?.addEventListener('click',e=>{if(e.target.closest('a'))setTimeout(()=>toggleMenu(false),0)});
matchMedia('(min-width:701px)').addEventListener('change',()=>toggleMenu(false));
const sectorButtons=[...document.querySelectorAll('.sector-select')];
function selectSector(index){sectorButtons.forEach((button,i)=>{const active=i===index;button.setAttribute('aria-pressed',String(active));button.closest('.sector-option').classList.toggle('active',active);const panel=document.getElementById(button.getAttribute('aria-controls'));panel.hidden=!active;if(active)panel.classList.add('reveal')})}
sectorButtons.forEach((button,i)=>{button.addEventListener('click',()=>selectSector(i));button.addEventListener('keydown',e=>{let next;if(e.key==='ArrowDown')next=(i+1)%sectorButtons.length;if(e.key==='ArrowUp')next=(i-1+sectorButtons.length)%sectorButtons.length;if(e.key==='Home')next=0;if(e.key==='End')next=sectorButtons.length-1;if(next!==undefined){e.preventDefault();sectorButtons[next].focus();selectSector(next)}})});
document.querySelectorAll('form[data-submit-once]').forEach(form=>form.addEventListener('submit',()=>{if(form.checkValidity()){const button=form.querySelector('button[type="submit"]');if(button){button.disabled=true;button.setAttribute('aria-busy','true')}}}));
window.addEventListener('pageshow',()=>document.querySelectorAll('button[aria-busy=true]').forEach(button=>{button.disabled=false;button.removeAttribute('aria-busy')}));
