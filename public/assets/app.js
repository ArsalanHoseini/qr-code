const sidebar=document.querySelector('#sidebar'),scrim=document.querySelector('[data-scrim]');
document.querySelector('[data-menu]')?.addEventListener('click',()=>{sidebar.classList.add('open');scrim.classList.add('show')});
scrim?.addEventListener('click',()=>{sidebar.classList.remove('open');scrim.classList.remove('show')});
document.addEventListener('click',e=>{const btn=e.target.closest('[data-popover]');document.querySelectorAll('.actions.open').forEach(x=>{if(x!==btn?.parentElement)x.classList.remove('open')});if(btn){e.preventDefault();btn.parentElement.classList.toggle('open')}});
const dialog=document.querySelector('#confirmDialog');
document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',e=>{if(form.dataset.confirmed)return;e.preventDefault();document.querySelector('#confirmText').textContent=form.dataset.confirm;dialog.showModal();dialog.addEventListener('close',()=>{if(dialog.returnValue==='confirm'){form.dataset.confirmed='1';form.submit()}},{once:true})}));

const qrForm=document.querySelector('[data-qr-form]');
if(qrForm){
  const preview=document.querySelector('[data-designer-preview]'),placeholder=document.querySelector('[data-preview-placeholder]'),loader=document.querySelector('[data-preview-loading]'),safety=document.querySelector('[data-safety]'),testResult=document.querySelector('[data-test-result]');
  let timer,previewUrl,controller;
  const fa=n=>String(n).replace(/[0-9]/g,d=>'۰۱۲۳۴۵۶۷۸۹'[d]);
  const updateOutputs=()=>qrForm.querySelectorAll('input[type=range]').forEach(input=>{const out=input.parentElement.querySelector('[data-range-output]');if(out)out.textContent=fa(input.value)+(input.name.includes('angle')?'°':input.name==='logo_size'?'٪':'px')});
  const updateColors=()=>qrForm.querySelectorAll('.color-field').forEach(field=>field.querySelector('code').textContent=field.querySelector('input').value);
  const showSafety=(status,score)=>{const labels={safe:'قابل اسکن',warning:'ممکن است اسکن سخت شود',unsafe:'این تنظیمات ممکن است QR را غیرقابل اسکن کند'};safety.className='safety-card '+status;safety.querySelector('b').textContent=labels[status]||labels.warning;safety.querySelector('[data-safety-score]').textContent=fa(score||0)};
  const requestPreview=async(format='svg')=>{
    controller?.abort();controller=new AbortController();loader.classList.add('show');
    const data=new FormData(qrForm);data.set('preview_format',format);
    try{const response=await fetch(qrForm.dataset.previewUrl,{method:'POST',body:data,signal:controller.signal,credentials:'same-origin'});if(!response.ok)throw new Error(await response.text());const blob=await response.blob();if(format==='png')return blob;if(previewUrl)URL.revokeObjectURL(previewUrl);previewUrl=URL.createObjectURL(blob);preview.src=previewUrl;preview.hidden=false;placeholder.hidden=true;showSafety(response.headers.get('X-QR-Safety')||'warning',response.headers.get('X-QR-Safety-Score')||0)}catch(error){if(error.name!=='AbortError'){placeholder.hidden=false;placeholder.textContent='پیش‌نمایش ساخته نشد؛ ورودی‌ها را بررسی کنید.'}}finally{loader.classList.remove('show')}
  };
  const schedule=()=>{clearTimeout(timer);timer=setTimeout(()=>requestPreview(),380)};
  qrForm.querySelectorAll('[data-design-tab]').forEach(btn=>btn.addEventListener('click',()=>{qrForm.querySelectorAll('[data-design-tab]').forEach(x=>x.classList.toggle('active',x===btn));qrForm.querySelectorAll('[data-design-panel]').forEach(x=>x.classList.toggle('active',x.dataset.designPanel===btn.dataset.designTab))}));
  const presets={
    classic:{dot_style:'square',finder_style:'square',finder_dot_style:'square',color_mode:'solid',foreground_color:'#111827',gradient_end_color:'#315ce8',background_mode:'solid',background_color:'#ffffff',corner_color:'#111827',corner_dot_color:'#111827',frame_style:'none'},
    minimal:{dot_style:'rounded',finder_style:'rounded',finder_dot_style:'rounded',color_mode:'solid',foreground_color:'#1f2937',background_mode:'solid',background_color:'#ffffff',corner_color:'#1f2937',corner_dot_color:'#1f2937',frame_style:'none'},
    modern:{dot_style:'dot',finder_style:'rounded',finder_dot_style:'dot',color_mode:'solid',foreground_color:'#173b65',background_mode:'solid',background_color:'#f8fafc',corner_color:'#315ce8',corner_dot_color:'#173b65',frame_style:'rounded'},
    gradient:{dot_style:'extra_rounded',finder_style:'rounded',finder_dot_style:'dot',color_mode:'linear',foreground_color:'#315ce8',gradient_end_color:'#7c3aed',background_mode:'solid',background_color:'#ffffff',corner_color:'#315ce8',corner_dot_color:'#7c3aed',frame_style:'none'},
    branded:{dot_style:'classy_rounded',finder_style:'outpoint',finder_dot_style:'rounded',color_mode:'linear',foreground_color:'#101a36',gradient_end_color:'#315ce8',background_mode:'solid',background_color:'#ffffff',corner_color:'#101a36',corner_dot_color:'#315ce8',frame_style:'branded',frame_text:'SCAN ME'},
    logo:{dot_style:'rounded',finder_style:'rounded',finder_dot_style:'dot',color_mode:'solid',foreground_color:'#111827',background_mode:'solid',background_color:'#ffffff',corner_color:'#111827',corner_dot_color:'#111827',frame_style:'none',error_correction:'H'},
    rounded:{dot_style:'extra_rounded',finder_style:'dot',finder_dot_style:'dot',color_mode:'solid',foreground_color:'#0f766e',background_mode:'solid',background_color:'#f0fdfa',corner_color:'#0f766e',corner_dot_color:'#0f766e',frame_style:'rounded'}
  };
  qrForm.querySelectorAll('[name=preset]').forEach(radio=>radio.addEventListener('change',()=>{const values=presets[radio.value];Object.entries(values).forEach(([name,value])=>{const input=qrForm.elements[name];if(input)input.value=value});updateOutputs();updateColors();schedule()}));
  qrForm.querySelector('[name=logo]')?.addEventListener('change',e=>{const file=e.target.files[0],img=document.querySelector('[data-logo-preview]');if(file){img.src=URL.createObjectURL(file);img.hidden=false;const correction=qrForm.elements.error_correction;if(['L','M'].includes(correction.value))correction.value='H'}schedule()});
  qrForm.addEventListener('input',e=>{if(e.target.matches('[type=range]'))updateOutputs();if(e.target.matches('[type=color]'))updateColors();const dynamic=qrForm.querySelector('[name=type]:checked')?.value==='dynamic';const contentType=qrForm.querySelector('[data-content-type]');if(contentType)contentType.style.display=dynamic?'none':'';schedule()});
  qrForm.querySelector('[data-test-qr]')?.addEventListener('click',async()=>{testResult.hidden=false;testResult.className='scan-test-result';testResult.textContent='در حال آزمایش رمزگشایی…';if(!('BarcodeDetector'in window)){testResult.classList.add('warning');testResult.textContent='این مرورگر BarcodeDetector ندارد؛ فایل را با دوربین تلفن آزمایش کنید.';return}try{const blob=await requestPreview('png');const bitmap=await createImageBitmap(blob);const detector=new BarcodeDetector({formats:['qr_code']});const codes=await detector.detect(bitmap);if(codes.length){testResult.classList.add('passed');testResult.textContent='آزمایش موفق — مقدار خوانده شد: '+codes[0].rawValue}else{testResult.classList.add('failed');testResult.textContent='آزمایش ناموفق — این طرح را ساده‌تر یا کنتراست را بیشتر کنید.'}}catch(e){testResult.classList.add('failed');testResult.textContent='آزمایش انجام نشد.'}});
  updateOutputs();updateColors();schedule();
}
