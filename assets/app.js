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
  // Sprach-Umschalter (lädt mit ?lang= neu, Cookie setzt der Server)
  var langSel=document.getElementById('lang');
  if(langSel) langSel.addEventListener('change',function(){
    var self=langSel.getAttribute('data-self')||'';
    location.href=self+'?lang='+encodeURIComponent(langSel.value);
  });
  // „Weitere Optionen" im Anlege-Formular: Auf/Zu-Zustand pro Browser merken
  var addopts=document.getElementById('addopts');
  if(addopts){
    try{ if(localStorage.getItem('goto-addopts')==='1') addopts.open=true; }catch(e){}
    addopts.addEventListener('toggle',function(){
      try{ localStorage.setItem('goto-addopts',addopts.open?'1':'0'); }catch(e){}
    });
  }

  // Diagnose: HTTP-Prüfungen per fetch, sobald der Bereich geöffnet wird
  var diag=document.getElementById('diag');
  if(diag){
    var diagRan=false;
    var diagSet=function(row,ok,txt){
      var s=row.querySelector('.diagstat');
      if(s){ s.classList.remove('diagstat--pend');
        s.classList.add(ok?'diagstat--ok':'diagstat--err');
        s.textContent=ok?(diag.getAttribute('data-l-ok')||'OK'):(diag.getAttribute('data-l-err')||'Fehler'); }
      var d=row.querySelector('.diagdetail'); if(d) d.textContent=txt||'';
    };
    diag.addEventListener('toggle',function(){
      if(!diag.open||diagRan) return; diagRan=true;
      diag.querySelectorAll('tr[data-diag-fetch]').forEach(function(row){
        var url=row.getAttribute('data-diag-fetch'), mode=row.getAttribute('data-diag-expect');
        fetch(url,{cache:'no-store'}).then(function(r){
          if(mode==='blocked'){ diagSet(row, r.status!==200, 'HTTP '+r.status); }
          else{ var viaApp=r.headers.get('X-Goto-App')==='1';
                diagSet(row, r.status===404&&viaApp, 'HTTP '+r.status+(viaApp?' (GOTO)':'')); }
        }).catch(function(){ diagSet(row, mode==='blocked', ''); });
      });
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
    if(selcount){ var tpl=selcount.getAttribute('data-tpl')||'%d markiert'; selcount.textContent=tpl.replace('%d',n); }
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

  // Suche + Filter + Sortierung
  var search=document.getElementById('search');
  var sortSel=document.getElementById('sort');
  var filterSel=document.getElementById('filter');
  if(search){
    var secs=Array.prototype.slice.call(document.querySelectorAll('section.group[data-group]'));
    var nores=document.getElementById('noresults');
    var num=function(r,a){ return parseInt(r.getAttribute(a),10)||0; };
    function applyView(){
      var q=(search.value||'').trim().toLowerCase();
      var sort=sortSel?sortSel.value:'new';
      var filter=filterSel?filterSel.value:'all';
      var any=false;
      document.querySelectorAll('table').forEach(function(tbl){
        var rows=Array.prototype.slice.call(tbl.querySelectorAll('tr[data-search]'));
        // sortieren
        rows.sort(function(a,b){
          if(sort==='clicks') return num(b,'data-clicks')-num(a,'data-clicks');
          if(sort==='az') return a.getAttribute('data-slug').localeCompare(b.getAttribute('data-slug'));
          if(sort==='old') return num(a,'data-created')-num(b,'data-created');
          return num(b,'data-created')-num(a,'data-created'); // new
        });
        rows.forEach(function(r){
          var exp=r.getAttribute('data-expired')==='1';
          var okF=(filter==='all')||(filter==='expired'&&exp)||(filter==='active'&&!exp);
          var okS=(q===''||r.getAttribute('data-search').indexOf(q)>=0);
          r.hidden=!(okF&&okS); if(!r.hidden) any=true;
          tbl.appendChild(r); // in sortierter Reihenfolge neu anhängen
        });
      });
      secs.forEach(function(s){ var vis=s.querySelectorAll('tr[data-search]:not([hidden])').length;
        s.hidden=((q!==''||filter!=='all')&&vis===0); });
      if(nores) nores.hidden=any||(q===''&&filter==='all');
    }
    search.addEventListener('input',applyView);
    if(sortSel) sortSel.addEventListener('change',applyView);
    if(filterSel) filterSel.addEventListener('change',applyView);
    applyView();
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

  // Klick-Verlauf-Dialog (Balken-Chart der letzten N Tage, Daten aus data-days)
  var cdlg=document.getElementById('clkdlg');
  if(cdlg){
    var cChartEl=document.getElementById('clkChart'), cTipEl=document.getElementById('clkTip'),
        cTitle=document.getElementById('clkTitle'), cTotal=document.getElementById('clkTotal'),
        cToday=document.getElementById('clkToday'), cEmpty=document.getElementById('clkEmpty'),
        cSeg=document.getElementById('clkRange');
    var cDays={}, cN=14, cLabel=cTitle.textContent,
        cLang=cdlg.getAttribute('data-lang')||'de',
        cCalls=cdlg.getAttribute('data-l-calls')||'Aufrufe';
    function isoLocal(d){ return d.getFullYear()+'-'+('0'+(d.getMonth()+1)).slice(-2)+'-'+('0'+d.getDate()).slice(-2); }
    function fmtDay(iso){ try{ return new Date(iso+'T12:00:00')
        .toLocaleDateString(cLang==='en'?'en-GB':'de-DE',{weekday:'short',day:'numeric',month:'short'});
      }catch(_){ return iso; } }
    function renderChart(){
      var n=cN, W=cChartEl.clientWidth||460, H=180, padT=20, padB=20;
      var keys=[], vals=[], sum=0;
      for(var i=n-1;i>=0;i--){ var k=isoLocal(new Date(Date.now()-i*86400000));
        keys.push(k); var v=+(cDays[k]||0); vals.push(v); sum+=v; }
      var max=Math.max.apply(null,vals.concat([1]));
      var plotH=H-padT-padB, step=W/n, gap=Math.min(3,Math.max(1,step*.2)), bw=Math.max(1,step-gap);
      var ns='http://www.w3.org/2000/svg';
      function el(tag,attrs){ var e=document.createElementNS(ns,tag);
        for(var a in attrs) e.setAttribute(a,attrs[a]); return e; }
      var svg=el('svg',{viewBox:'0 0 '+W+' '+H});
      // Zurückhaltendes Raster: Grundlinie + gestrichelte Maximal-Linie mit Wert
      svg.appendChild(el('line',{x1:0,y1:padT,x2:W,y2:padT,'class':'cgrid','stroke-dasharray':'3 3'}));
      svg.appendChild(el('line',{x1:0,y1:H-padB,x2:W,y2:H-padB,'class':'cgrid'}));
      var maxLab=el('text',{x:0,y:padT-6,'class':'clab'}); maxLab.textContent=max; svg.appendChild(maxLab);
      // Dünne Balken, oben abgerundet, an der Grundlinie verankert
      var bars=[];
      for(var b1=0;b1<n;b1++){
        var h=vals[b1]/max*plotH, r=null;
        if(vals[b1]>0){ r=el('rect',{x:b1*step+gap/2,y:H-padB-h,width:bw,height:h,
          rx:Math.min(3,bw/2),'class':'cbar'}); svg.appendChild(r); }
        bars.push(r);
      }
      // X-Beschriftung: erster / mittlerer / letzter Tag
      [[0,'start'],[Math.floor(n/2),'middle'],[n-1,'end']].forEach(function(p){
        var tx=el('text',{x:p[0]*step+step/2,y:H-6,'class':'clab','text-anchor':p[1]});
        tx.textContent=fmtDay(keys[p[0]]); svg.appendChild(tx);
      });
      // Hover: spaltenbreite Trefferflächen (größer als der Balken) + Tooltip
      for(var b2=0;b2<n;b2++)(function(i){
        var hit=el('rect',{x:i*step,y:0,width:step,height:H,fill:'transparent'});
        hit.addEventListener('mouseenter',function(){
          if(bars[i]) bars[i].classList.add('hot');
          cTipEl.textContent=fmtDay(keys[i])+' · '+vals[i]+' '+cCalls;
          var box=cChartEl.getBoundingClientRect();
          var px=(i*step+step/2)/W*box.width;
          var py=(H-padB-(vals[i]/max*plotH))/H*box.height;
          cTipEl.style.left=Math.max(46,Math.min(box.width-46,px))+'px';
          cTipEl.style.top=Math.max(26,py)+'px';
          cTipEl.classList.add('on');
        });
        hit.addEventListener('mouseleave',function(){
          if(bars[i]) bars[i].classList.remove('hot');
          cTipEl.classList.remove('on');
        });
        svg.appendChild(hit);
      })(b2);
      cChartEl.innerHTML=''; cChartEl.appendChild(svg);
      cEmpty.hidden=sum>0;
    }
    document.querySelectorAll('button.clicks[data-days]').forEach(function(b){
      b.addEventListener('click',function(){
        try{ cDays=JSON.parse(b.getAttribute('data-days'))||{}; }catch(_){ cDays={}; }
        if(Array.isArray(cDays)) cDays={};
        cTitle.textContent=cLabel+': '+(b.getAttribute('data-slug')||'');
        cTotal.textContent=b.getAttribute('data-total')||'0';
        cToday.textContent=String(cDays[isoLocal(new Date())]||0);
        if(cdlg.showModal) cdlg.showModal(); else cdlg.setAttribute('open','');
        renderChart();
      });
    });
    cSeg.querySelectorAll('button').forEach(function(b){
      b.addEventListener('click',function(){
        cN=parseInt(b.getAttribute('data-n'),10)||14;
        cSeg.querySelectorAll('button').forEach(function(x){ x.classList.toggle('on',x===b); });
        renderChart();
      });
    });
    document.getElementById('clkClose').addEventListener('click',function(){ cdlg.close(); });
    cdlg.addEventListener('click',function(e){ if(e.target===cdlg) cdlg.close(); });
  }

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

    // --- Batch: alle QR-Codes als ZIP (Store-Methode, lokal erzeugt) ---
    function crc32(b){var c,crc=0xFFFFFFFF;for(var i=0;i<b.length;i++){c=(crc^b[i])&0xFF;for(var k=0;k<8;k++)c=(c&1)?(0xEDB88320^(c>>>1)):(c>>>1);crc=(crc>>>8)^c;}return (crc^0xFFFFFFFF)>>>0;}
    function makeZip(files){
      var enc=new TextEncoder(),chunks=[],central=[],offset=0;
      function u16(n){return [n&0xFF,(n>>>8)&0xFF];}
      function u32(n){return [n&0xFF,(n>>>8)&0xFF,(n>>>16)&0xFF,(n>>>24)&0xFF];}
      files.forEach(function(f){
        var name=enc.encode(f.name),crc=crc32(f.data),size=f.data.length;
        var lfh=[].concat(u32(0x04034b50),u16(20),u16(0),u16(0),u16(0),u16(0),u32(crc),u32(size),u32(size),u16(name.length),u16(0));
        var h=new Uint8Array(lfh.length+name.length);h.set(lfh,0);h.set(name,lfh.length);
        chunks.push(h,f.data);
        var cdh=[].concat(u32(0x02014b50),u16(20),u16(20),u16(0),u16(0),u16(0),u16(0),u32(crc),u32(size),u32(size),u16(name.length),u16(0),u16(0),u16(0),u16(0),u32(0),u32(offset));
        var cd=new Uint8Array(cdh.length+name.length);cd.set(cdh,0);cd.set(name,cdh.length);central.push(cd);
        offset+=h.length+size;
      });
      var cdSize=0;central.forEach(function(c){cdSize+=c.length;});
      var eocd=new Uint8Array([].concat(u32(0x06054b50),u16(0),u16(0),u16(files.length),u16(files.length),u32(cdSize),u32(offset),u16(0)));
      var all=chunks.concat(central,[eocd]),total=0;all.forEach(function(a){total+=a.length;});
      var out=new Uint8Array(total),p=0;all.forEach(function(a){out.set(a,p);p+=a.length;});
      return out;
    }
    function plainCanvas(qr,scale,margin){
      var n=qr.size, dim=(n+margin*2)*scale;
      var c=document.createElement('canvas'); c.width=dim; c.height=dim;
      var x2=c.getContext('2d'); x2.fillStyle='#fff'; x2.fillRect(0,0,dim,dim); x2.fillStyle='#000';
      for(var y=0;y<n;y++) for(var x=0;x<n;x++) if(qr.modules[y][x]) x2.fillRect((x+margin)*scale,(y+margin)*scale,scale,scale);
      return c;
    }
    function canvasBytes(c){ return new Promise(function(res){ c.toBlob(function(bl){ bl.arrayBuffer().then(function(buf){ res(new Uint8Array(buf)); }); },'image/png'); }); }
    var zipBtn=document.getElementById('qrAllZip');
    if(zipBtn) zipBtn.addEventListener('click',function(){
      var items=Array.prototype.slice.call(document.querySelectorAll('[data-qr]'));
      if(!items.length){ alert('Keine Links vorhanden.'); return; }
      var ecl=elEcl?elEcl.value:'M', done=0, old=zipBtn.textContent;
      zipBtn.disabled=true; zipBtn.textContent='Erzeuge … 0/'+items.length;
      var jobs=[];
      items.forEach(function(b){
        var slug=b.getAttribute('data-slug')||'qr', url=b.getAttribute('data-qr'), qr;
        try{ qr=QRCodeGen.encode(url, ecl); }catch(e){ return; }
        jobs.push(canvasBytes(plainCanvas(qr,8,4)).then(function(bytes){
          zipBtn.textContent='Erzeuge … '+(++done)+'/'+items.length;
          return {name:'qr-'+slug+'.png', data:bytes};
        }));
      });
      Promise.all(jobs).then(function(files){
        dl('goto-qrcodes.zip', new Blob([makeZip(files)],{type:'application/zip'}));
        zipBtn.disabled=false; zipBtn.textContent=old;
      }).catch(function(err){ alert(err.message); zipBtn.disabled=false; zipBtn.textContent=old; });
    });
  }
})();
