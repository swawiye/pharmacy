const API = 'api.php';
      let currentPage = 'dashboard';
      let allMedicines = [];
      let allCategories = [];

      // Auth Check
      async function checkAuth() {
        try {
          const res = await fetch(`${API}?action=me`);
          const data = await res.json();
          if (data.error) {
            window.location.href = 'login.html';
            return false;
          }
          const uName = document.getElementById('user-name');
          const uAvatar = document.getElementById('user-avatar');
          if (uName) uName.textContent = data.username;
          if (uAvatar) uAvatar.textContent = data.username.charAt(0).toUpperCase();
          return true;
        } catch(e) {
          window.location.href = 'login.html';
          return false;
        }
      }

      async function logout() {
        await fetch(`${API}?action=logout`);
        window.location.href = 'login.html';
      }

      // Navigation
      function showPage(page) {
        document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
        document.getElementById('page-' + page).classList.add('active');
        document.querySelectorAll('.nav-item').forEach(n => {
          if (n.textContent.toLowerCase().includes(page === 'dashboard' ? 'dashboard' : page)) {
            n.classList.add('active');
          }
        });
        const titles = { dashboard: 'Dashboard', medicines: 'Medicines', sales: 'Sales', categories: 'Categories' };
        document.getElementById('page-title').textContent = titles[page] || page;
        currentPage = page;
        loadPage(page);
      }

      function loadPage(page) {
        if (page === 'dashboard') loadDashboard();
        else if (page === 'medicines') { loadCategories(); loadMedicines(); }
        else if (page === 'sales') loadSales();
        else if (page === 'categories') loadCategories(true);
      }

      function refreshCurrentPage() { loadPage(currentPage); }

      // API Helper
      async function api(action, method = 'GET', body = null) {
        const opts = { method, headers: { 'Content-Type': 'application/json' } };
        if (body) opts.body = JSON.stringify(body);
        const res = await fetch(`${API}?action=${action}`, opts);
        return res.json();
      }

      // Toast
      function toast(msg, type = 'info') {
        const el = document.createElement('div');
        el.className = `toast ${type}`;
        el.innerHTML = (type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️') + ' ' + msg;
        document.getElementById('toast-container').appendChild(el);
        setTimeout(() => el.remove(), 3500);
      }

      // Modal
      function openModal(id) { document.getElementById(id).classList.add('open'); }
      function closeModal(id) { document.getElementById(id).classList.remove('open'); }

      function confirmDelete(msg, callback) {
        document.getElementById('confirm-message').textContent = msg;
        document.getElementById('confirm-btn').onclick = () => { closeModal('confirm-modal'); callback(); };
        openModal('confirm-modal');
      }

      // Dashboard
      async function loadDashboard() {
        const data = await api('dashboard');

        document.getElementById('stats-grid').innerHTML = `
          <div class="stat-card">
            <div class="stat-icon blue">💊</div>
            <div><div class="stat-value">${data.total_medicines}</div><div class="stat-label">Total Medicines</div></div>
          </div>
          <div class="stat-card">
            <div class="stat-icon red">⚠️</div>
            <div><div class="stat-value">${data.low_stock}</div><div class="stat-label">Low Stock Items</div></div>
          </div>
          <div class="stat-card">
            <div class="stat-icon amber">📅</div>
            <div><div class="stat-value">${data.expired}</div><div class="stat-label">Expired Medicines</div></div>
          </div>
          <div class="stat-card">
            <div class="stat-icon green">💰</div>
            <div><div class="stat-value">KES ${parseFloat(data.today_sales).toLocaleString()}</div><div class="stat-label">Today's Sales</div></div>
          </div>
          <div class="stat-card">
            <div class="stat-icon cyan">📈</div>
            <div><div class="stat-value">KES ${parseFloat(data.monthly_sales).toLocaleString()}</div><div class="stat-label">Monthly Sales</div></div>
          </div>
        `;

        // Update badge
        const badge = document.getElementById('low-stock-badge');
        if (data.low_stock > 0) { badge.style.display = ''; badge.textContent = data.low_stock; }
        else badge.style.display = 'none';

        // Low stock list
        const lowEl = document.getElementById('low-stock-list');
        if (!data.low_stock_items.length) {
          lowEl.innerHTML = '<div class="empty-state"><div class="empty-icon">✅</div><p>All stock levels are healthy</p></div>';
        } else {
          lowEl.innerHTML = data.low_stock_items.map(m => {
            const pct = Math.min(100, Math.round((m.quantity / m.low_stock_threshold) * 100));
            const color = pct <= 0 ? '#ef4444' : pct < 50 ? '#f59e0b' : '#10b981';
            return `<div style="display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid var(--border);">
              <div style="flex:1">
                <div style="font-weight:600;font-size:13px">${m.name}</div>
                <div style="color:var(--text-muted);font-size:12px">${m.quantity} ${m.unit} remaining</div>
              </div>
              <div class="stock-bar">
                <div class="stock-fill"><div class="stock-fill-inner" style="width:${pct}%;background:${color}"></div></div>
              </div>
              <span class="badge ${pct <= 0 ? 'badge-danger' : 'badge-warning'}">${pct <= 0 ? 'OUT' : 'LOW'}</span>
            </div>`;
          }).join('');
        }

        // Recent sales
        const salesEl = document.getElementById('recent-sales-list');
        if (!data.recent_sales.length) {
          salesEl.innerHTML = '<div class="empty-state"><div class="empty-icon">📭</div><p>No sales recorded yet</p></div>';
        } else {
          salesEl.innerHTML = '<table style="width:100%;border-collapse:collapse">' +
            data.recent_sales.map(s => `
              <tr style="border-bottom:1px solid var(--border)">
                <td style="padding:8px 4px;font-size:13px;font-weight:600">${s.medicine_name}</td>
                <td style="padding:8px 4px;font-size:12px;color:var(--text-muted)">x${s.quantity_sold}</td>
                <td style="padding:8px 4px;font-size:13px;font-weight:600;color:var(--success)">KES ${parseFloat(s.total_amount).toLocaleString()}</td>
                <td style="padding:8px 4px;font-size:11px;color:var(--text-light)">${new Date(s.sale_date).toLocaleDateString()}</td>
              </tr>`).join('') + '</table>';
          
            salesEl.innerHTML = `
            <table style="width:100%; border-collapse:collapse">
              ${data.recent_sales.map(s => `
                <tr style="border-bottom:1px solid var(--border)">
                  <td style="padding:10px 4px; font-size:13px;">
                    <div style="font-weight:500">${s.medicine_name}</div>
                    <div style="color:var(--text-muted); font-size:11px">${s.sale_date}</div>
                  </td>
                  <td style="padding:10px 4px; text-align:right; font-weight:600">
                    KES ${parseFloat(s.total_amount).toLocaleString()} 
                  </td>
                </tr>
              `).join('')}
            </table>`;
        } 
      }

      // Medicines
        async function loadMedicines() {
          const search = document.getElementById('med-search').value;
          const cat = document.getElementById('med-category-filter').value;
          const stock = document.getElementById('med-stock-filter').value;

          const data = await api(`medicines&search=${search}&category=${cat}&stock=${stock}`);
          const tbody = document.getElementById('medicines-table-body');

          if (!data.length) {
            tbody.innerHTML = '<tr><td colspan="10" style="text-align:center; padding:20px;">No medicines found.</td></tr>';
            return;
          }
        
          tbody.innerHTML = data.map((m, i) => `
            <tr>
              <td>${i + 1}</td>
              <td><strong>${m.name}</strong><br><small>${m.generic_name || ''}</small></td>
              <td>${m.category_name}</td>
              <td>${m.batch_number}</td>
              <td>${m.quantity} ${m.unit}</td>
              <td>${m.purchase_price}</td>
              <td>${m.selling_price}</td>
              <td>${m.expiry_date}</td>
              <td><span class="badge ${m.status === 'Expired' ? 'badge-danger' : 'badge-success'}">${m.status}</span></td>
              <td>
                <button class="btn-icon" onclick="editMedicine(${m.id})">✏️</button>
                <button class="btn-icon" onclick="deleteMedicine(${m.id})">🗑️</button>
              </td>
            </tr>
          `).join('');
        }

      async function openMedicineModal(id = null) {
        document.getElementById('medicine-form').reset();
        document.getElementById('med-id').value = '';
        document.getElementById('medicine-form-alert').innerHTML = '';
        document.getElementById('medicine-modal-title').textContent = 'Add Medicine';

        // Populate selects
        const [cats, sups] = await Promise.all([
          api('get_categories')
        ]);
        populateSelect('med-category', cats, 'id', 'name', '— Category —');

        if (id) {
          const med = await api(`get_medicine&id=${id}`);
          if (med) {
            document.getElementById('medicine-modal-title').textContent = 'Edit Medicine';
            document.getElementById('med-id').value       = med.id;
            document.getElementById('med-name').value     = med.name;
            document.getElementById('med-generic').value  = med.generic_name || '';
            document.getElementById('med-category').value = med.category_id || '';
            document.getElementById('med-batch').value    = med.batch_number || '';
            document.getElementById('med-quantity').value = med.quantity;
            document.getElementById('med-unit').value     = med.unit;
            document.getElementById('med-purchase-price').value = med.purchase_price;
            document.getElementById('med-selling-price').value  = med.selling_price;
            document.getElementById('med-expiry').value   = med.expiry_date || '';
            document.getElementById('med-threshold').value = med.low_stock_threshold;
            document.getElementById('med-description').value = med.description || '';
          }
        }
        openModal('medicine-modal');
      }

      async function editMedicine(id) { openMedicineModal(id); }

      async function saveMedicine() {
        const alertEl = document.getElementById('medicine-form-alert');
        const formData = {
          id: document.getElementById('med-id').value,
          name: document.getElementById('med-name').value.trim(),
          generic_name: document.getElementById('med-generic').value.trim(),
          category_id: document.getElementById('med-category').value,
          batch_number: document.getElementById('med-batch').value.trim(),
          quantity: document.getElementById('med-quantity').value,
          unit: document.getElementById('med-unit').value,
          purchase_price: document.getElementById('med-purchase-price').value,
          selling_price: document.getElementById('med-selling-price').value,
          expiry_date: document.getElementById('med-expiry').value,
          low_stock_threshold: document.getElementById('med-threshold').value,
          description: document.getElementById('med-description').value.trim(),
        };
        if (!data.name) { alertEl.innerHTML = '<div class="alert alert-danger">Medicine name is required.</div>'; return; }
        const result = await api('save_medicine', 'POST', formData);
        if (result.success) {
          toast('Medicine saved successfully', 'success');
          closeModal('medicine-modal');
          loadMedicines();
        } else {
          toast(result.error || 'Failed to save', 'error');
        }
        if (currentPage === 'dashboard') loadDashboard();
      }

      function deleteMedicine(id, name) {
        confirmDelete(`Delete "${name}"? This cannot be undone.`, async () => {
          await api(`delete_medicine&id=${id}`, 'DELETE');
          toast('Medicine deleted', 'success');
          loadMedicines();
        });
      }

      // Categories
      async function loadCategories(renderTable = false) {
        const data = await api('get_categories');
        allCategories = data;

        // Populate filter select on medicines page
        const filterSel = document.getElementById('med-category-filter');
        if (filterSel) {
          const val = filterSel.value;
          filterSel.innerHTML = '<option value="">All Categories</option>' +
            data.map(c => `<option value="${c.id}" ${c.id == val ? 'selected' : ''}>${c.name}</option>`).join('');
        }

        if (!renderTable) return;
        const tbody = document.getElementById('categories-table-body');
        if (!data.length) {
          tbody.innerHTML = '<tr><td colspan="4"><div class="empty-state"><div class="empty-icon">🏷️</div><p>No categories yet</p></div></td></tr>';
          return;
        }
        tbody.innerHTML = data.map((c, i) => `
          <tr>
            <td style="color:var(--text-muted)">${i+1}</td>
            <td><strong>${c.name}</strong></td>
            <td style="color:var(--text-muted)">${c.description || '—'}</td>
            <td><div class="actions">
              <button class="btn btn-outline btn-xs" onclick="editCategory(${c.id},'${escapeAttr(c.name)}','${escapeAttr(c.description||'')}')">✏️</button>
              <button class="btn btn-danger btn-xs" onclick="deleteCategory(${c.id},'${escapeAttr(c.name)}')">🗑️</button>
            </div></td>
          </tr>`).join('');
      }

      function openCategoryModal() {
        document.getElementById('category-id').value = '';
        document.getElementById('category-name').value = '';
        document.getElementById('category-desc').value = '';
        document.getElementById('category-form-alert').innerHTML = '';
        document.getElementById('category-modal-title').textContent = 'Add Category';
        openModal('category-modal');
      }

      function editCategory(id, name, desc) {
        document.getElementById('category-id').value = id;
        document.getElementById('category-name').value = name;
        document.getElementById('category-desc').value = desc;
        document.getElementById('category-form-alert').innerHTML = '';
        document.getElementById('category-modal-title').textContent = 'Edit Category';
        openModal('category-modal');
      }

      async function saveCategory() {
        const alertEl = document.getElementById('category-form-alert');
        const data = {
          id: document.getElementById('category-id').value,
          name: document.getElementById('category-name').value.trim(),
          description: document.getElementById('category-desc').value.trim(),
        };
        if (!data.name) { alertEl.innerHTML = '<div class="alert alert-danger">Category name required.</div>'; return; }
        const res = await api('save_category', 'POST', data);
        if (res.error) { alertEl.innerHTML = `<div class="alert alert-danger">${res.error}</div>`; return; }
        closeModal('category-modal');
        toast('Category saved', 'success');
        loadCategories(true);
      }

      function deleteCategory(id, name) {
        confirmDelete(`Delete category "${name}"?`, async () => {
          await api(`delete_category&id=${id}`, 'DELETE');
          toast('Category deleted', 'success');
          loadCategories(true);
        });
      }

      // Sales
      async function loadSales() {
        const dateFrom = document.getElementById('sales-date-from')?.value || '';
        const dateTo   = document.getElementById('sales-date-to')?.value   || '';
        const params   = new URLSearchParams({ action: 'get_sales', date_from: dateFrom, date_to: dateTo });
        const data     = await (await fetch(`${API}?${params}`)).json();
        const tbody    = document.getElementById('sales-table-body');

        if (!data.length) {
          tbody.innerHTML = '<tr><td colspan="8"><div class="empty-state"><div class="empty-icon">📭</div><p>No sales records found</p></div></td></tr>';
          return;
        }
        let totalAmt = 0;
        tbody.innerHTML = data.map((s, i) => {
          totalAmt += parseFloat(s.total_amount);
          return `<tr>
            <td style="color:var(--text-muted)">${i+1}</td>
            <td><strong>${s.medicine_name}</strong></td>
            <td>${s.quantity_sold}</td>
            <td>KES ${parseFloat(s.unit_price).toLocaleString()}</td>
            <td style="font-weight:600;color:var(--success)">KES ${parseFloat(s.total_amount).toLocaleString()}</td>
            <td>${s.customer_name || '—'}</td>
            <td>${s.sold_by || '—'}</td>
            <td style="font-size:12px;color:var(--text-muted)">${new Date(s.sale_date).toLocaleString()}</td>
          </tr>`;
        }).join('') +
        `<tr style="background:var(--surface2)">
          <td colspan="4" style="font-weight:700;text-align:right;padding:10px 14px">Total</td>
          <td style="font-weight:700;color:var(--success)">KES ${totalAmt.toLocaleString()}</td>
          <td colspan="3"></td>
        </tr>`;
      }

      function clearSalesFilter() {
        document.getElementById('sales-date-from').value = '';
        document.getElementById('sales-date-to').value = '';
        loadSales();
      }

      async function openSaleModal() {
        document.getElementById('sale-form-alert').innerHTML = '';
        document.getElementById('sale-qty').value = 1;
        document.getElementById('sale-price').value = 0;
        document.getElementById('sale-customer').value = '';
        document.getElementById('sale-total-display').innerHTML = 'Total: <strong>KES 0.00</strong>';

        const meds = await api('get_medicines');
        populateSelect('sale-medicine', meds.filter(m => m.quantity > 0), 'id', 'name', '— Select Medicine —',
          m => `${m.name} (${m.quantity} ${m.unit} @ KES ${m.selling_price})`);

        // Update total on input change
        ['sale-qty','sale-price'].forEach(id => {
          document.getElementById(id).addEventListener('input', updateSaleTotal);
        });
        openModal('sale-modal');
      }

      function fillSalePrice() {
        const sel = document.getElementById('sale-medicine');
        const opt = sel.selectedOptions[0];
        // Extract price from label
        const match = opt?.text?.match(/@ KES ([\d.]+)/);
        if (match) document.getElementById('sale-price').value = match[1];
        updateSaleTotal();
      }

      function updateSaleTotal() {
        const qty   = parseFloat(document.getElementById('sale-qty').value) || 0;
        const price = parseFloat(document.getElementById('sale-price').value) || 0;
        document.getElementById('sale-total-display').innerHTML =
          `Total: <strong>KES ${(qty * price).toLocaleString(undefined, {minimumFractionDigits:2})}</strong>`;
      }

      async function recordSale() {
        const alertEl = document.getElementById('sale-form-alert');
        const data = {
          medicine_id:   document.getElementById('sale-medicine').value,
          quantity_sold: document.getElementById('sale-qty').value,
          unit_price:    document.getElementById('sale-price').value,
          customer_name: document.getElementById('sale-customer').value.trim(),
          sold_by:       document.getElementById('sale-by').value.trim(),
        };
        if (!data.medicine_id) { alertEl.innerHTML = '<div class="alert alert-danger">Please select a medicine.</div>'; return; }
        if (data.quantity_sold < 1) { alertEl.innerHTML = '<div class="alert alert-danger">Quantity must be at least 1.</div>'; return; }
        const res = await api('record_sale', 'POST', data);
        if (res.error) { alertEl.innerHTML = `<div class="alert alert-danger">${res.error}</div>`; return; }
        closeModal('sale-modal');
        toast(`Sale recorded! Total: KES ${parseFloat(res.total).toLocaleString()}`, 'success');
        loadSales();
        if (currentPage === 'dashboard') loadDashboard();
      }

      // Helpers
      function populateSelect(id, items, valKey, labelKey, placeholder, labelFn = null) {
        const sel = document.getElementById(id);
        if (!sel) return;
        sel.innerHTML = `<option value="">${placeholder}</option>` +
          items.map(item => `<option value="${item[valKey]}">${labelFn ? labelFn(item) : item[labelKey]}</option>`).join('');
      }

      function escapeAttr(str) {
        return String(str).replace(/'/g, "\\'").replace(/"/g, '&quot;');
      }

      // Initialization
      checkAuth().then(isLoggedIn => {
        if (isLoggedIn) loadDashboard();
      });