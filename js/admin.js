/* Baiku — Phase 5 admin dashboard */
(() => {
  const api = 'admin_api.php';
  const state = { products: [], orders: [], customers: [] };
  const $ = (s) => document.querySelector(s);
  const money = (n) => `Rs. ${Number(n || 0).toLocaleString('en-IN', {maximumFractionDigits: 2})}`;
  const date = (s) => s ? new Date(s.replace(' ', 'T')).toLocaleDateString('en-GB', {day:'2-digit',month:'short',year:'numeric'}) : '—';
  const esc = (s) => String(s ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  function message(text='', type=''){ const el=$('#adminMessage'); el.textContent=text; el.className='auth-message'+(type?' '+type:''); }
  function setupAdminAccountMenu(){
    const trigger=$('#adminAccount');
    if(!trigger) return;
    const close=()=>{document.querySelector('.baiku-admin-account-menu')?.remove();trigger.setAttribute('aria-expanded','false');};
    const toggle=()=>{
      const existing=document.querySelector('.baiku-admin-account-menu');
      if(existing){close();return;}
      const menu=document.createElement('div');
      menu.className='baiku-account-menu baiku-admin-account-menu';
      menu.setAttribute('role','menu');
      menu.innerHTML='<div class=\"account-menu-name\">Baiku Admin</div><div class=\"account-menu-role\">Administrator</div><div class=\"account-menu-divider\"></div><a href=\"admin.php\" role=\"menuitem\">Admin dashboard →</a><a href=\"index.html\" role=\"menuitem\">Storefront →</a><button type=\"button\" class=\"account-menu-signout\" role=\"menuitem\">Sign out</button>';
      document.body.appendChild(menu);
      const rect=trigger.getBoundingClientRect();
      menu.style.top=`${Math.round(rect.bottom+10+window.scrollY)}px`;
      menu.style.right=`${Math.max(14,Math.round(window.innerWidth-rect.right))}px`;
      trigger.setAttribute('aria-expanded','true');
      menu.querySelector('.account-menu-signout').addEventListener('click',async()=>{
        const button=menu.querySelector('.account-menu-signout');button.disabled=true;button.textContent='Signing out…';
        try{await fetch('api.php?action=logout',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:'{}'});}finally{window.location.href='login.html';}
      });
    };
    trigger.addEventListener('click',e=>{e.preventDefault();toggle();});
    document.addEventListener('click',e=>{const menu=document.querySelector('.baiku-admin-account-menu');if(menu&&!menu.contains(e.target)&&!trigger.contains(e.target))close();});
    window.addEventListener('resize',close);window.addEventListener('scroll',close,{passive:true});
  }
  async function request(action, options={}){
    const query = options.query || '';
    const requestOptions = {...options}; delete requestOptions.query;
    const response = await fetch(`${api}?action=${encodeURIComponent(action)}${query}`, {credentials:'same-origin', headers:{'Content-Type':'application/json'}, ...requestOptions});
    let data; try { data=await response.json(); } catch { throw new Error('The server returned an invalid response.'); }
    if(!response.ok || !data.success){ if(response.status===403) window.location.href='login.html?admin=required'; throw new Error(data.message||'Admin request failed.'); }
    return data;
  }
  async function loadDashboard(){
    const d=await request('dashboard');
    $('#statProducts').textContent=d.stats.products;
    $('#statOrders').textContent=d.stats.orders;
    $('#statCustomers').textContent=d.stats.customers;
    $('#statRevenue').textContent=money(d.stats.revenue);
    $('#recentOrders').innerHTML=d.recent_orders.length ? d.recent_orders.map(o=>`<tr><td>#BK-${String(o.id).padStart(4,'0')}</td><td>${esc(o.customer_name)}</td><td>${money(o.total_amount)}</td><td><span class="status-pill status-${o.status.toLowerCase()}">${esc(o.status)}</span></td><td>${date(o.created_at)}</td></tr>`).join('') : '<tr><td colspan="5">No orders yet.</td></tr>';
  }
  async function loadProducts(){
    const d=await request('products'); state.products=d.products;
    $('#productsTable').innerHTML=d.products.length ? d.products.map(p=>`<tr class="${p.active?'':'is-inactive'}"><td><strong>${esc(p.name)}</strong><small>${esc(p.description)}</small></td><td>${esc(p.category)}</td><td>${money(p.price)}</td><td>${p.stock}</td><td>${p.featured?'Yes':'—'}</td><td>${p.active?'Active':'Inactive'}</td><td class="action-cell"><button class="table-action" data-edit="${p.id}">Edit</button>${p.active?`<button class="table-action danger" data-delete="${p.id}">Remove</button>`:`<button class="table-action" data-restore="${p.id}">Restore</button>`}</td></tr>`).join('') : '<tr><td colspan="7">No products found.</td></tr>';
  }
  async function loadOrders(){
    const d=await request('orders'); state.orders=d.orders;
    $('#ordersTable').innerHTML=d.orders.length ? d.orders.map(o=>`<tr><td>#BK-${String(o.id).padStart(4,'0')}</td><td><strong>${esc(o.customer_name)}</strong><small>${esc(o.customer_email)}</small></td><td>${money(o.total_amount)}</td><td>${esc(o.payment_method)}</td><td><select class="status-select" data-status="${o.id}">${['Pending','Confirmed','Processing','Shipped','Delivered','Cancelled'].map(s=>`<option ${s===o.status?'selected':''}>${s}</option>`).join('')}</select></td><td>${date(o.created_at)}</td><td><button class="table-action" data-order="${o.id}">Details</button></td></tr>`).join('') : '<tr><td colspan="7">No orders yet. Orders created at checkout will appear here.</td></tr>';
  }
  async function loadCustomers(){
    const d=await request('customers'); state.customers=d.customers;
    $('#customersTable').innerHTML=d.customers.length ? d.customers.map(c=>`<tr><td><strong>${esc(c.name)}</strong></td><td>${esc(c.email)}</td><td>${c.order_count}</td><td>${money(c.spent)}</td><td>${date(c.created_at)}</td></tr>`).join('') : '<tr><td colspan="5">No customer accounts yet.</td></tr>';
  }
  async function loadAll(){ message('Refreshing dashboard…'); try { await loadDashboard(); message('Dashboard updated.','success'); } catch(e){ message(e.message,'error'); } }
  function showTab(tab){ document.querySelectorAll('[data-admin-tab]').forEach(b=>b.classList.toggle('active',b.dataset.adminTab===tab)); document.querySelectorAll('[data-panel]').forEach(p=>p.hidden=p.dataset.panel!==tab); if(tab==='products')loadProducts().catch(e=>message(e.message,'error')); if(tab==='orders')loadOrders().catch(e=>message(e.message,'error')); if(tab==='customers')loadCustomers().catch(e=>message(e.message,'error')); }
  function openProduct(product=null){ $('#productModal').classList.add('open'); $('#productModalTitle').textContent=product?'Edit product':'Add product'; $('#productId').value=product?.id||''; $('#productName').value=product?.name||''; $('#productDescription').value=product?.description||''; $('#productPrice').value=product?.price??''; $('#productStock').value=product?.stock??0; $('#productCategory').value=product?.category||'Helmets'; $('#productImage').value=product?.image||''; $('#productFeatured').checked=!!product?.featured; $('#productActive').checked=product ? !!product.active : true; }
  function closeProduct(){ $('#productModal').classList.remove('open'); }
  async function saveProduct(e){ e.preventDefault(); const id=$('#productId').value; const payload={id:id?Number(id):undefined,name:$('#productName').value.trim(),description:$('#productDescription').value.trim(),price:Number($('#productPrice').value),stock:Number($('#productStock').value),category:$('#productCategory').value,image:$('#productImage').value.trim(),featured:$('#productFeatured').checked,active:$('#productActive').checked}; const btn=$('#saveProduct'); btn.disabled=true; btn.textContent='Saving…'; try { await request(id?'update_product':'create_product',{method:'POST',body:JSON.stringify(payload)}); closeProduct(); await loadProducts(); await loadDashboard(); message(id?'Product updated.':'Product created.','success'); } catch(e){ message(e.message,'error'); } finally { btn.disabled=false; btn.textContent='Save product →'; } }
  async function changeStatus(id,status){ try { await request('update_order_status',{method:'POST',body:JSON.stringify({id:Number(id),status})}); await loadOrders(); await loadDashboard(); message('Order status updated.','success'); } catch(e){ message(e.message,'error'); } }
  async function showOrder(id){ const order=state.orders.find(o=>Number(o.id)===Number(id)); if(!order)return; const d=await request('order_items', {query:`&id=${encodeURIComponent(id)}`}); $('#orderDetail').hidden=false; $('#orderDetail').innerHTML=`<div class="order-detail-head"><div><div class="section-kicker">Order #BK-${String(order.id).padStart(4,'0')}</div><h3>${esc(order.customer_name)}</h3></div><button class="table-action" id="closeOrderDetail">Close</button></div><p>${esc(order.address)}, ${esc(order.city)}, ${esc(order.country)}${order.phone?' · '+esc(order.phone):''}</p><div class="table-wrap"><table class="admin-table"><thead><tr><th>Item</th><th>Qty</th><th>Unit</th><th>Subtotal</th></tr></thead><tbody>${d.items.length?d.items.map(i=>`<tr><td>${esc(i.product_name)}</td><td>${i.quantity}</td><td>${money(i.unit_price)}</td><td>${money(i.subtotal)}</td></tr>`).join(''):'<tr><td colspan="4">No items recorded.</td></tr>'}</tbody></table></div>`; $('#closeOrderDetail').onclick=()=>$('#orderDetail').hidden=true; }
  document.addEventListener('click', async (e)=>{
    const tab=e.target.closest('[data-admin-tab]'); if(tab){showTab(tab.dataset.adminTab);return;}
    if(e.target.closest('#refreshAdmin')){await loadAll();return;}
    if(e.target.closest('#adminLogout')){try{await fetch('api.php?action=logout',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:'{}'});}finally{window.location.href='login.html';}return;}
    if(e.target.closest('#newProduct')){openProduct();return;}
    if(e.target.closest('#closeProductModal')||e.target.closest('#cancelProduct')){closeProduct();return;}
    const edit=e.target.closest('[data-edit]'); if(edit){const p=state.products.find(x=>Number(x.id)===Number(edit.dataset.edit));if(p)openProduct(p);return;}
    const del=e.target.closest('[data-delete]'); if(del && confirm('Remove this product from the storefront? Existing order history will be kept.')){try{await request('delete_product',{method:'POST',body:JSON.stringify({id:Number(del.dataset.delete)})});await loadProducts();await loadDashboard();message('Product removed.','success');}catch(e){message(e.message,'error');}return;}
    const restore=e.target.closest('[data-restore]'); if(restore){const p=state.products.find(x=>Number(x.id)===Number(restore.dataset.restore));if(p){p.active=true;openProduct(p);}return;}
    const order=e.target.closest('[data-order]'); if(order){try{await showOrder(order.dataset.order);}catch(e){message(e.message,'error');}return;}
  });
  document.addEventListener('change',(e)=>{const s=e.target.closest('[data-status]');if(s)changeStatus(s.dataset.status,s.value);});
  $('#productForm').addEventListener('submit',saveProduct); $('#productModal').addEventListener('click',e=>{if(e.target.id==='productModal')closeProduct();});
  setupAdminAccountMenu();
  loadDashboard().catch(e=>message(e.message,'error'));
})();
