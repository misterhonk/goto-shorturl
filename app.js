(function(){
  document.querySelectorAll('form[data-confirm]').forEach(function(f){
    f.addEventListener('submit',function(e){ if(!confirm(f.getAttribute('data-confirm'))) e.preventDefault(); });
  });
  document.querySelectorAll('button[data-confirm]').forEach(function(b){
    b.addEventListener('click',function(e){ if(!confirm(b.getAttribute('data-confirm'))) e.preventDefault(); });
  });
  document.querySelectorAll('[data-copy]').forEach(function(b){
    b.addEventListener('click',function(){
      navigator.clipboard.writeText(b.getAttribute('data-copy')).then(function(){
        b.classList.add('ok'); setTimeout(function(){ b.classList.remove('ok'); },1200);
      });
    });
  });
  document.querySelectorAll('select[data-autosubmit]').forEach(function(s){
    s.addEventListener('change',function(){ s.form.submit(); });
  });
  document.querySelectorAll('img.fav').forEach(function(im){
    im.addEventListener('error',function(){ im.classList.add('hide'); });
  });
  // Theme-Umschalter (System / Hell / Dunkel)
  var themeSel=document.getElementById('theme');
  if(themeSel){
    var cur='system'; try{ cur=localStorage.getItem('goto-theme')||'system'; }catch(e){}
    themeSel.value=cur;
    themeSel.addEventListener('change',function(){
      var v=themeSel.value;
      try{ localStorage.setItem('goto-theme',v); }catch(e){}
      if(v==='system') document.documentElement.removeAttribute('data-theme');
      else document.documentElement.setAttribute('data-theme',v);
    });
  }
  // Ablaufdatum zurücksetzen
  document.querySelectorAll('[data-clear-date]').forEach(function(b){
    b.addEventListener('click',function(){
      var f=b.closest('.datefield'), inp=f&&f.querySelector('input[type=date]');
      if(inp){ inp.value=''; inp.focus(); }
    });
  });

  // Bulk-Auswahl
  var boxes=Array.prototype.slice.call(document.querySelectorAll('.rowchk'));
  var selall=document.getElementById('selall'), selcount=document.getElementById('selcount');
  function upd(){ var n=boxes.filter(function(b){return b.checked;}).length;
    if(selcount) selcount.textContent=n+' markiert';
    if(selall) selall.checked=(n>0&&n===boxes.length); }
  boxes.forEach(function(b){ b.addEventListener('change',upd); });
  if(selall) selall.addEventListener('change',function(){ boxes.forEach(function(b){ b.checked=selall.checked; }); upd(); });
  var bulk=document.getElementById('bulk');
  if(bulk) bulk.addEventListener('submit',function(e){
    if(!boxes.some(function(b){return b.checked;})){ e.preventDefault(); alert('Bitte zuerst Links markieren.'); }
  });

  // Inline-URL-Validierung
  function isUrl(v){ try{ var u=new URL(v); return u.protocol==='http:'||u.protocol==='https:'; }catch(_){ return false; } }
  document.querySelectorAll('input[name=url]').forEach(function(inp){
    function chk(){ var v=inp.value.trim();
      inp.classList.toggle('valid', v!=='' && isUrl(v));
      inp.classList.toggle('invalid', v!=='' && !isUrl(v)); }
    inp.addEventListener('input',chk); chk();
  });

  // Live-Suche
  var search=document.getElementById('search');
  if(search){
    var rows=Array.prototype.slice.call(document.querySelectorAll('tr[data-search]'));
    var secs=Array.prototype.slice.call(document.querySelectorAll('section.group[data-group]'));
    var nores=document.getElementById('noresults');
    search.addEventListener('input',function(){
      var q=search.value.trim().toLowerCase(), any=false;
      rows.forEach(function(r){ var m=(q===''||r.getAttribute('data-search').indexOf(q)>=0);
        r.hidden=!m; if(m) any=true; });
      secs.forEach(function(s){ var vis=s.querySelectorAll('tr[data-search]:not([hidden])').length;
        s.hidden=(q!==''&&vis===0); });
      if(nores) nores.hidden=!(q!==''&&!any);
    });
  }

  // Drag & Drop zwischen Gruppen
  var dnd=document.getElementById('dndform');
  if(dnd){
    var dragSlug=null;
    document.querySelectorAll('tr[data-slug]').forEach(function(tr){
      tr.addEventListener('dragstart',function(e){
        if(e.target.closest('input,select,button,a,.movecell')){ e.preventDefault(); return; }
        dragSlug=tr.getAttribute('data-slug'); tr.classList.add('dragging');
        e.dataTransfer.effectAllowed='move';
        try{ e.dataTransfer.setData('text/plain',dragSlug); }catch(_){}
      });
      tr.addEventListener('dragend',function(){ tr.classList.remove('dragging'); dragSlug=null;
        document.querySelectorAll('.dropok').forEach(function(s){ s.classList.remove('dropok'); }); });
    });
    document.querySelectorAll('section.group[data-group]').forEach(function(sec){
      sec.addEventListener('dragover',function(e){ if(dragSlug!==null){ e.preventDefault(); sec.classList.add('dropok'); } });
      sec.addEventListener('dragleave',function(e){ if(!sec.contains(e.relatedTarget)) sec.classList.remove('dropok'); });
      sec.addEventListener('drop',function(e){ e.preventDefault(); if(dragSlug===null) return;
        dnd.querySelector('[name=slug]').value=dragSlug;
        dnd.querySelector('[name=group]').value=sec.getAttribute('data-group');
        dnd.submit();
      });
    });
  }

  // Toasts
  var tc=document.getElementById('toasts');
  if(tc){
    Array.prototype.slice.call(tc.children).forEach(function(t,i){
      requestAnimationFrame(function(){ t.classList.add('toast--in'); });
      var to=setTimeout(function(){ hideToast(t); }, 4200+i*250);
      t.addEventListener('click',function(){ clearTimeout(to); hideToast(t); });
    });
  }
  function hideToast(t){ t.classList.add('toast--out'); setTimeout(function(){ if(t.parentNode) t.remove(); },350); }

  // QR-Dialog
  var dlg=document.getElementById('qrdlg');
  if(dlg && window.QRCodeGen){
    var prev=document.getElementById('qrPrev'),
        elEcl=document.getElementById('qrEcl'), elScale=document.getElementById('qrScale'),
        elMargin=document.getElementById('qrMargin'), elFg=document.getElementById('qrFg'),
        elBg=document.getElementById('qrBg'), elUrl=document.getElementById('qrUrl'),
        elTitle=document.getElementById('qrTitle');
    var cur={url:'',slug:'qr'};
    function num(el,d){ var v=parseInt(el.value,10); return isNaN(v)?d:v; }
    function draw(qr,scale,margin){
      var n=qr.size, dim=(n+margin*2)*scale;
      var c=document.createElement('canvas'); c.width=dim; c.height=dim;
      var x2=c.getContext('2d');
      x2.fillStyle=elBg.value; x2.fillRect(0,0,dim,dim);
      x2.fillStyle=elFg.value;
      for(var y=0;y<n;y++) for(var x=0;x<n;x++) if(qr.modules[y][x])
        x2.fillRect((x+margin)*scale,(y+margin)*scale,scale,scale);
      return c;
    }
    function svg(qr,scale,margin){
      var n=qr.size, dim=(n+margin*2)*scale, r='';
      for(var y=0;y<n;y++){ var x=0; while(x<n){ if(qr.modules[y][x]){ var w=1;
        while(x+w<n&&qr.modules[y][x+w]) w++;
        r+='<rect x="'+((x+margin)*scale)+'" y="'+((y+margin)*scale)+'" width="'+(w*scale)+'" height="'+scale+'"/>'; x+=w;
      } else x++; } }
      return '<svg xmlns="http://www.w3.org/2000/svg" width="'+dim+'" height="'+dim+'" viewBox="0 0 '+dim+' '+dim+'" shape-rendering="crispEdges">'
        +'<rect width="'+dim+'" height="'+dim+'" fill="'+elBg.value+'"/><g fill="'+elFg.value+'">'+r+'</g></svg>';
    }
    function dl(name,blob){ var u=URL.createObjectURL(blob),a=document.createElement('a');
      a.href=u; a.download=name; document.body.appendChild(a); a.click();
      setTimeout(function(){ URL.revokeObjectURL(u); a.remove(); },150); }
    function render(){
      if(!cur.url) return;
      try{ var qr=QRCodeGen.encode(cur.url, elEcl.value), m=Math.max(0,num(elMargin,4));
        var ps=Math.max(1,Math.floor(200/(qr.size+m*2)));
        prev.innerHTML=''; prev.appendChild(draw(qr,ps,m));
      }catch(err){ prev.textContent=err.message; }
    }
    document.querySelectorAll('[data-qr]').forEach(function(b){
      b.addEventListener('click',function(){
        cur.url=b.getAttribute('data-qr'); cur.slug=b.getAttribute('data-slug')||'qr';
        elTitle.textContent='QR-Code: '+cur.slug; elUrl.textContent=cur.url;
        render(); if(dlg.showModal) dlg.showModal(); else dlg.setAttribute('open','');
      });
    });
    [elEcl,elScale,elMargin,elFg,elBg].forEach(function(el){ el.addEventListener('input',render); });
    document.getElementById('qrClose').addEventListener('click',function(){ dlg.close(); });
    dlg.addEventListener('click',function(e){ if(e.target===dlg) dlg.close(); });
    document.getElementById('qrPng').addEventListener('click',function(){
      try{ var qr=QRCodeGen.encode(cur.url,elEcl.value);
        draw(qr,Math.max(2,num(elScale,8)),Math.max(0,num(elMargin,4)))
          .toBlob(function(bl){ dl('qr-'+cur.slug+'.png',bl); },'image/png');
      }catch(err){ alert(err.message); }
    });
    document.getElementById('qrSvg').addEventListener('click',function(){
      try{ var qr=QRCodeGen.encode(cur.url,elEcl.value);
        dl('qr-'+cur.slug+'.svg', new Blob([svg(qr,Math.max(2,num(elScale,8)),Math.max(0,num(elMargin,4)))],{type:'image/svg+xml'}));
      }catch(err){ alert(err.message); }
    });
  }
})();
