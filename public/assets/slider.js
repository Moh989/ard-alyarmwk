/** Inline films only load when selected and visible; user motion/data preferences take priority. */
function createShowcaseVideo(slide) {
  const video=slide.querySelector('[data-showcase-video]');
  if (!video) return null;
  const screen=slide.querySelector('[data-video-screen]');
  const poster=screen.querySelector('img');
  const toggle=slide.querySelector('[data-video-toggle]');
  const progress=slide.querySelector('[data-video-progress]');
  const error=slide.querySelector('[data-video-error]');
  const motion=matchMedia('(prefers-reduced-motion: reduce)');
  const connection=navigator.connection;
  let selected=false, visible=false, intent='auto', pending=false, denied=false, failed=false, finished=false;
  let posterReady=!poster || (poster.complete && poster.naturalWidth>0);
  const saveData=()=>Boolean(connection?.saveData || /(^|-)2g$/.test(connection?.effectiveType||''));
  const wanted=()=>selected && visible && !document.hidden && posterReady && !failed && !finished && (intent==='play' || (intent==='auto' && !motion.matches && !saveData() && !denied));
  function restart(){if(finished||video.ended){video.currentTime=0;finished=false;progress.value=0}}
  function buttonState() {
    const playing=!video.paused && !video.ended;
    toggle.setAttribute('aria-label',playing?toggle.dataset.pauseLabel:toggle.dataset.playLabel);
    toggle.title=playing?toggle.dataset.pauseLabel:toggle.dataset.playLabel;
    toggle.querySelector('[data-video-play-icon]').toggleAttribute('hidden',playing);
    toggle.querySelector('[data-video-pause-icon]').toggleAttribute('hidden',!playing);
    slide.dataset.playback=playing?'playing':'paused';
  }
  function revealFrame() {
    if (video.readyState>=2 && !failed) screen.classList.add('has-frame');
  }
  async function sync() {
    if (!wanted()) {video.pause();buttonState();return}
    if (!video.getAttribute('src')) {
      const small=innerWidth<=700 || saveData();
      video.src=small?video.dataset.videoMobile:video.dataset.videoDesktop;
      video.dataset.quality=small?'480p':'720p';
    }
    if (!video.paused || pending) return;
    pending=true;toggle.setAttribute('aria-busy','true');
    try {await video.play();if(!wanted())video.pause()}
    catch(reason) {if(reason.name==='NotAllowedError'){denied=true;intent='pause'}}
    finally {pending=false;toggle.removeAttribute('aria-busy');buttonState()}
  }
  video.hidden=false;video.muted=true;video.defaultMuted=true;video.controls=false;
  toggle.hidden=false;buttonState();
  toggle.addEventListener('click',event=>{
    event.stopPropagation();
    if (!video.paused || pending) intent='pause';
    else {restart();intent='play';denied=false}
    sync();
  });
  video.addEventListener('playing',()=>{
    if(!wanted()){video.pause();return}
    if('requestVideoFrameCallback' in video)video.requestVideoFrameCallback(revealFrame);
    else revealFrame();
    buttonState();
  });
  video.addEventListener('pause',buttonState);
  video.addEventListener('ended',()=>{
    finished=true;buttonState();
    slide.dispatchEvent(new CustomEvent('showcase:videoended',{bubbles:true}));
  });
  video.addEventListener('timeupdate',()=>{
    if(Number.isFinite(video.duration))progress.max=video.duration;
    progress.value=video.currentTime;
  });
  video.addEventListener('error',()=>{
    failed=true;video.pause();video.hidden=true;screen.classList.remove('has-frame');toggle.hidden=true;error.hidden=false;
    slide.dispatchEvent(new CustomEvent('showcase:videoerror',{bubbles:true}));
  });
  function posterLoaded(){posterReady=true;sync()}
  if(poster&&!posterReady){poster.addEventListener('load',posterLoaded,{once:true});poster.addEventListener('error',posterLoaded,{once:true})}
  document.addEventListener('visibilitychange',sync);
  motion.addEventListener('change',sync);
  connection?.addEventListener?.('change',sync);
  if('IntersectionObserver' in window){
    new IntersectionObserver(entries=>{visible=entries[0].intersectionRatio>=.5;sync()},{threshold:[0,.5]}).observe(screen);
  }else{
    const check=()=>{const b=screen.getBoundingClientRect();visible=b.top<innerHeight && b.bottom>0;sync()};
    addEventListener('scroll',check,{passive:true});addEventListener('resize',check);check();
  }
  return {
    setActive(value){if(value&&!selected)restart();selected=value;sync()},
    hasEnded:()=>finished,
    hasFailed:()=>failed,
    playForCycle(){intent='play';denied=false;sync()}
  };
}

/* Film carousels advance on the real ended event, then give each photograph six seconds. */
document.querySelectorAll('[data-showcase]').forEach(root => {
  const stage = root.querySelector('[data-slide-stage]');
  const slides = [...root.querySelectorAll('[data-slide]')];
  const tabs = [...root.querySelectorAll('[data-slide-to]')];
  const controls = root.querySelector('[data-slide-controls]');
  const films = slides.map(createShowcaseVideo);
  const status = root.querySelector('[data-slide-status]');
  const rtl = document.documentElement.dir === 'rtl';
  const autoButton=root.querySelector('[data-slide-autoplay]');
  const motion=matchMedia('(prefers-reduced-motion: reduce)');
  const connection=navigator.connection;
  const saveData=()=>Boolean(connection?.saveData || /(^|-)2g$/.test(connection?.effectiveType||''));
  let automatic=Boolean(autoButton && !motion.matches && !saveData());
  let onScreen=false, timer=null, userStarted=false;
  let active = 0;
  let request = 0;
  const canAdvance=()=>automatic && onScreen && !document.hidden;
  function stopClock(){clearTimeout(timer);timer=null;tabs.forEach(tab=>tab.classList.remove('is-counting'))}
  function autoState(){
    if(!autoButton)return;
    const label=automatic?autoButton.dataset.autoPause:autoButton.dataset.autoPlay;
    autoButton.setAttribute('aria-label',label);autoButton.title=label;
    autoButton.querySelector('[data-auto-pause-icon]').toggleAttribute('hidden',!automatic);
    autoButton.querySelector('[data-auto-play-icon]').toggleAttribute('hidden',automatic);
    root.dataset.autoplay=automatic?'running':'paused';
  }
  function schedule(){
    stopClock();autoState();
    if(!canAdvance())return;
    const film=films[active];
    if(film?.hasEnded()){show(active+1,false,true);return}
    if(film&&!film.hasFailed())return;
    // The current video is never shortened by a fixed slide timer.
    tabs[active]?.classList.add('is-counting');
    timer=setTimeout(()=>{timer=null;if(canAdvance())show(active+1,false,true)},6000);
  }
  async function show(index, announce = true, automated = false) {
    if(automated&&!canAdvance())return;
    const next = (index + slides.length) % slides.length;
    const ticket = ++request;
    if (announce && next === active) {root.removeAttribute('aria-busy');return}
    const img = slides[next].querySelector('img');
    if (img) img.loading = 'eager';
    stopClock();
    if ((announce || automated) && img && (!img.complete || !img.naturalWidth)) {
      // Retain the current photograph until the next one is decoded, even on a slow connection.
      root.setAttribute('aria-busy', 'true');
      await img.decode().catch(() => {});
      if (ticket !== request) return;
    }
    root.removeAttribute('aria-busy');
    if(automated&&!canAdvance())return;
    // Keep keyboard focus out of the outgoing slide, including when swiping.
    if (slides[active].contains(document.activeElement) && next !== active) tabs[next]?.focus({preventScroll:true});
    active = next;
    slides.forEach((slide, i) => {
      slide.setAttribute('aria-hidden', String(i !== active));
      slide.inert = i !== active;
      slide.classList.toggle('is-active', i === active);
    });
    tabs.forEach((tab, i) => tab.setAttribute('aria-current', String(i === active)));
    films.forEach((film,i)=>film?.setActive(i===active));
    if(automatic&&userStarted)films[active]?.playForCycle();
    if (announce) status.textContent = slides[active].getAttribute('aria-label');
    schedule();
  }
  root.classList.add('showcase-ready');
  show(0, false);
  if (!controls) return;
  controls.hidden = false;
  if(autoButton){
    autoButton.addEventListener('click',()=>{
      automatic=!automatic;
      if(automatic)userStarted=true;
      if(automatic&&!films[active]?.hasEnded())films[active]?.playForCycle();
      schedule();
    });
    root.addEventListener('showcase:videoended',event=>{if(event.target===slides[active])schedule()});
    root.addEventListener('showcase:videoerror',event=>{if(event.target===slides[active])schedule()});
    document.addEventListener('visibilitychange',schedule);
    const preferencesChanged=()=>{if(motion.matches||saveData())automatic=false;schedule()};
    motion.addEventListener('change',preferencesChanged);
    connection?.addEventListener?.('change',preferencesChanged);
    // Keyboard readers retain control; restarting requires the explicit autoplay button.
    root.addEventListener('focusin',event=>{
      if(event.target!==autoButton&&event.target.matches(':focus-visible')){automatic=false;schedule()}
    });
    if('IntersectionObserver' in window){
      new IntersectionObserver(entries=>{onScreen=entries[0].intersectionRatio>=.35;schedule()},{threshold:[0,.35]}).observe(stage);
    }else{
      const check=()=>{const box=stage.getBoundingClientRect();onScreen=box.top<innerHeight&&box.bottom>0;schedule()};
      addEventListener('scroll',check,{passive:true});addEventListener('resize',check);check();
    }
  }
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(entries => {
      if (!entries.some(entry => entry.isIntersecting)) return;
      // Warm the small set of images only as this carousel approaches the viewport.
      slides.slice(1).forEach(slide => {const img=slide.querySelector('img');if(img) img.loading='eager'});
      observer.disconnect();
    }, {rootMargin:'120px'});
    observer.observe(root);
  }
  stage.tabIndex = 0;
  stage.setAttribute('aria-label', rtl ? 'منطقة الشرائح. استخدم السهمين يمين ويسار للتنقل.' : 'Slides. Use the left and right arrow keys to navigate.');
  root.querySelector('[data-slide-prev]').addEventListener('click', () => show(active - 1));
  root.querySelector('[data-slide-next]').addEventListener('click', () => show(active + 1));
  tabs.forEach((tab,i) => tab.addEventListener('click', () => show(i)));
  root.addEventListener('keydown', async event => {
    if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
    if(autoButton&&event.target!==autoButton){automatic=false;schedule()}
    let next;
    if (event.key === 'ArrowRight') next = active + (rtl ? -1 : 1);
    else if (event.key === 'ArrowLeft') next = active + (rtl ? 1 : -1);
    else if (event.key === 'Home') next = 0;
    else if (event.key === 'End') next = slides.length - 1;
    else return;
    event.preventDefault();
    await show(next);
    if (event.target.matches('[data-slide-to]')) tabs[active].focus({preventScroll:true});
  });
  let gesture = null;
  let suppressClickUntil = 0;
  stage.addEventListener('pointerdown', event => {
    if (!event.isPrimary || event.button !== 0) return;
    gesture = {id:event.pointerId, x:event.clientX, y:event.clientY, time:performance.now()};
  });
  stage.addEventListener('pointerup', event => {
    if (!gesture || gesture.id !== event.pointerId) return;
    const dx=event.clientX-gesture.x, dy=event.clientY-gesture.y, duration=performance.now()-gesture.time;
    gesture = null;
    if (Math.abs(dx)<45 || Math.abs(dx)<Math.abs(dy)*1.4 || duration>1400) return;
    suppressClickUntil = performance.now()+350;
    show(active + ((rtl ? dx>0 : dx<0) ? 1 : -1));
  });
  stage.addEventListener('pointercancel', () => {gesture=null});
  stage.addEventListener('pointerleave', event => {if(event.pointerType==='mouse') gesture=null});
  stage.addEventListener('dragstart', event => event.preventDefault());
  stage.addEventListener('click', event => {if(performance.now()<suppressClickUntil){event.preventDefault();event.stopPropagation()}}, true);
});
