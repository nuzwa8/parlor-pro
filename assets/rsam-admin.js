/**
 * Retail Shop Accounting & Management (RSAM)
 * Yeh file tamam (Admin UI) (interactions), (AJAX) (requests), 
 * (form handling), aur (table rendering) ko (manage) karti hai.
 */

// (IIFE) (Immediately Invoked Function Expression) tamam (scope) ko (wrap) karne ke liye
(() => {
	'use strict';

	/** Part 1 — Bunyadi Setup, Utilities, aur Main (Initializer) */

	// (Global Data) (PHP) se (wp_localize_script) ke zariye
	const rsamData = window.rsamData || {
		ajax_url: '',
		nonce: '',
		caps: {},
		strings: {
			loading: 'Loading...',
			errorOccurred: 'An error occurred.',
			confirmDelete: 'Are you sure?',
			noItemsFound: 'No items found.',
		},
	};

	// (Global State)
	const state = {
		currentScreen: null, // (e.g., 'dashboard', 'products')
		currentPage: 1, // (Pagination) ke liye
		currentSearch: '', // (Search) ke liye
		isLoading: false,
		// (UI Elements) (Cache)
		ui: {
			root: null,
			modal: null,
			confirmModal: null,
		},
	};

	// DOM Ready Hone Par (Initialize) Karein
	document.addEventListener('DOMContentLoaded', () => {
		// (Root element) (find) karein
		const rootEl = document.querySelector('.rsam-root[data-screen]');
		if (!rootEl) {
			// Agar (root) nahi mila to kuch na karein
			return;
		}

		state.ui.root = rootEl;
		state.currentScreen = rootEl.dataset.screen;

		// (Screen) ke hisab se (initializer) (call) karein
		initApp();
	});

	/**
	 * Main (App Initializer)
	 * (Screen) ke hisab se (routing) karta hai.
	 */
	function initApp() {
		if (!state.ui.root) return;

		// (Common UI) (templates) (Modals) ko (mount) karein
		initCommonUI();

		// (Screen-specific) (initializer) (call) karein
		switch (state.currentScreen) {
			case 'dashboard':
				initDashboard();
				break;
			case 'products':
				initProducts();
				break;
			case 'purchases':
				initPurchases();
				break;
			case 'sales':
				initSales();
				break;
			case 'expenses':
				initExpenses();
				break;
			case 'employees':
				initEmployees();
				break;
			case 'suppliers':
				initSuppliers();
				break;
			case 'customers':
				initCustomers();
				break;
			case 'reports':
				initReports();
				break;
			case 'settings':
				initSettings();
				break;
			default:
				showError(
					`Unknown screen: ${state.currentScreen}`,
					state.ui.root
				);
		}
	}

	/**
	 * (Common UI Elements) (Modals) ko (mount) karta hai.
	 */
	function initCommonUI() {
		// (Add/Edit Modal)
		const modalTmpl = document.getElementById('rsam-tmpl-modal-form');
		if (modalTmpl) {
			const modalEl = mountTemplate(modalTmpl);
			document.body.appendChild(modalEl);
			state.ui.modal = {
				wrapper: document.querySelector(
					'.rsam-modal-wrapper:not(.rsam-modal-confirm)'
				),
				backdrop: document.querySelector(
					'.rsam-modal-backdrop:not(.rsam-modal-confirm .rsam-modal-backdrop)'
				),
				title: document.querySelector('.rsam-modal-title'),
				body: document.querySelector('.rsam-modal-body'),
				saveBtn: document.querySelector('.rsam-modal-save'),
				cancelBtn: document.querySelector('.rsam-modal-cancel'),
				closeBtn: document.querySelector('.rsam-modal-close'),
			};
			// (Close) (listeners)
			state.ui.modal.backdrop.addEventListener('click', () =>
				closeModal()
			);
			state.ui.modal.cancelBtn.addEventListener('click', () =>
				closeModal()
			);
			state.ui.modal.closeBtn.addEventListener('click', () =>
				closeModal()
			);
		}

		// (Confirm Modal)
		const confirmTmpl = document.getElementById('rsam-tmpl-modal-confirm');
		if (confirmTmpl) {
			const confirmEl = mountTemplate(confirmTmpl);
			document.body.appendChild(confirmEl);
			state.ui.confirmModal = {
				wrapper: document.querySelector('.rsam-modal-confirm'),
				backdrop: document.querySelector(
					'.rsam-modal-confirm .rsam-modal-backdrop'
				),
				title: document.querySelector(
					'.rsam-modal-confirm .rsam-modal-title'
				),
				body: document.querySelector(
					'.rsam-modal-confirm .rsam-modal-body'
				),
				deleteBtn: document.querySelector(
					'.rsam-modal-confirm-delete'
				),
				cancelBtn: document.querySelector(
					'.rsam-modal-confirm .rsam-modal-cancel'
				),
				closeBtn: document.querySelector(
					'.rsam-modal-confirm .rsam-modal-close'
				),
			};
			// (Close) (listeners)
			state.ui.confirmModal.backdrop.addEventListener('click', () =>
				closeConfirmModal()
			);
			state.ui.confirmModal.cancelBtn.addEventListener('click', () =>
				closeConfirmModal()
			);
			state.ui.confirmModal.closeBtn.addEventListener('click', () =>
				closeConfirmModal()
			);
		}
	}

	// -----------------------------------------------------------------
	// (UTILITY FUNCTIONS)
	// -----------------------------------------------------------------

	/**
	 * (WordPress AJAX) ke liye (Wrapper)
	 * @param {string} action (WP AJAX action) ka naam
	 * @param {object} data Bhejne wala (data)
	 * @param {HTMLElement} [loaderEl] (Button) ya (element) jahan (loader) dikhana hai
	 * @returns {Promise<any>}
	 */
	async function wpAjax(action, data = {}, loaderEl = null) {
		if (state.isLoading && loaderEl) return Promise.reject('Loading...');

		// (Loader) (show) karein
		if (loaderEl) {
			setLoading(loaderEl, true);
		}
		state.isLoading = true;

		// (jQuery) ka (AJAX) istemal karein (WordPress (admin) mein (reliable) hai)
		return new Promise((resolve, reject) => {
			window
				.jQuery
				.post(rsamData.ajax_url, {
					action: action,
					nonce: rsamData.nonce,
					...data,
				})
				.done((response) => {
					if (response.success) {
						resolve(response.data);
					} else {
						// (PHP error) ko (reject) karein
						const errorMsg =
							response.data && response.data.message
								? response.data.message
								: rsamData.strings.errorOccurred;
						showToast(errorMsg, 'error');
						reject(errorMsg);
					}
				})
				.fail((jqXHR, textStatus, errorThrown) => {
					// (Network/HTTP error) ko (reject) karein
					console.error(
						'RSAM AJAX Error:',
						textStatus,
						errorThrown,
						jqXHR
					);
					const errorMsg =
						jqXHR.responseJSON && jqXHR.responseJSON.data
							? jqXHR.responseJSON.data.message
							: rsamData.strings.errorOccurred;
					showToast(errorMsg, 'error');
					reject(errorMsg);
				})
				.always(() => {
					// (Loader) (hide) karein
					if (loaderEl) {
						setLoading(loaderEl, false);
					}
					state.isLoading = false;
				});
		});
	}

	/**
	 * Ek (HTML <template>) ko (clone) aur (mount) karta hai.
	 * @param {HTMLTemplateElement} templateEl (Template element)
	 * @returns {DocumentFragment} (Cloned content)
	 */
	function mountTemplate(templateEl) {
		if (!templateEl || templateEl.tagName !== 'TEMPLATE') {
			console.error('Invalid template provided', templateEl);
			return document.createDocumentFragment();
		}
		return templateEl.content.cloneNode(true);
	}

	/**
	 * (Loader) (state) (set) karta hai (aam taur par (button) par).
	 * @param {HTMLElement} el (Element)
	 * @param {boolean} isLoading Kya (loading) (state) (set) karni hai?
	 */
	function setLoading(el, isLoading) {
		if (!el) return;
		el.disabled = isLoading;
		el.classList.toggle('rsam-loading', isLoading);
	}

	/**
	 * (Error message) (container) mein dikhata hai.
	 * @param {string} message (Error) ka paigham
	 * @param {HTMLElement} [container] (Container) (default: root)
	 */
	function showError(message, container = null) {
		const target = container || state.ui.root;
		if (target) {
			target.innerHTML = `<div class="rsam-alert rsam-alert-danger">${escapeHtml(
				message
			)}</div>`;
		}
		console.error('RSAM Error:', message);
	}

	/**
	 * (HTML) (strings) ko (escape) karta hai.
	 * @param {string} str
	 * @returns {string} (Escaped string)
	 */
	function escapeHtml(str) {
		if (str === null || str === undefined) return '';
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	/**
	 * Raqam (Price) ko format karta hai (Yeh (PHP helper) se (match) karta hai).
	 * (Note: (Currency symbol) (settings) se (configurable) hona chahiye)
	 * @param {number|string} price
	 * @returns {string} Formatted raqam
	 */
	function formatPrice(price) {
		const symbol = rsamData.strings.currencySymbol || 'Rs.'; // (Settings) se ayega
		const num = parseFloat(price);
		if (isNaN(num)) {
			return `${symbol} 0.00`;
		}
		return `${symbol} ${num.toLocaleString('en-IN', {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2,
		})}`;
	}

	/**
	 * (Toast) (notification) dikhata hai (Admin notices) ka istemal karke.
	 * @param {string} message Paigham
	 * @param {'success'|'error'|'warning'|'info'} type (Notice) ki (type)
	 */
	function showToast(message, type = 'success') {
		const notice = document.createElement('div');
		notice.className = `notice notice-${type} is-dismissible rsam-toast`;
		notice.innerHTML = `<p>${escapeHtml(message)}</p>`;

		// (Dismiss) (button) (WordPress (style))
		const dismissBtn = document.createElement('button');
		dismissBtn.type = 'button';
		dismissBtn.className = 'notice-dismiss';
		dismissBtn.innerHTML =
			'<span class="screen-reader-text">Dismiss this notice.</span>';
		notice.appendChild(dismissBtn);

		dismissBtn.addEventListener('click', () => {
			notice.remove();
		});

		// (WordPress header) ke (top) par (show) karein
		const headerEnd =
			document.querySelector('.wp-header-end') ||
			document.querySelector('.wrap');
		if (headerEnd) {
			headerEnd.insertAdjacentElement('afterend', notice);
		} else {
			document.body.prepend(notice);
		}

		// 3 (seconds) baad (auto-dismiss)
		setTimeout(() => {
			notice.remove();
		}, 3000);
	}

	/**
	 * (Modal) ko kholta hai aur (content) (set) karta hai.
	 * @param {string} title (Modal) ka (title)
	 * @param {HTMLElement|string} formContent (Form) ya (HTML content)
	 * @param {function} saveCallback (Save) (button) (click) hone par (callback)
	 */
	function openModal(title, formContent, saveCallback) {
		if (!state.ui.modal) return;

		state.ui.modal.title.innerHTML = escapeHtml(title);
		state.ui.modal.body.innerHTML = ''; // Pehle (clear) karein

		if (typeof formContent === 'string') {
			state.ui.modal.body.innerHTML = formContent;
		} else {
			state.ui.modal.body.appendChild(formContent);
		}

		// (Save) (listener) ko (bind) karein
		// Pehle purana (listener) (remove) karein (clone karke)
		const newSaveBtn = state.ui.modal.saveBtn.cloneNode(true);
		state.ui.modal.saveBtn.parentNode.replaceChild(
			newSaveBtn,
			state.ui.modal.saveBtn
		);
		state.ui.modal.saveBtn = newSaveBtn;
		state.ui.modal.saveBtn.addEventListener('click', saveCallback);

		document.body.classList.add('rsam-modal-open');
		state.ui.modal.wrapper.classList.add('rsam-modal-visible');
	}

	/**
	 * (Modal) ko (close) karta hai.
	 */
	function closeModal() {
		if (!state.ui.modal) return;
		document.body.classList.remove('rsam-modal-open');
		state.ui.modal.wrapper.classList.remove('rsam-modal-visible');
		// (Modal body) (clear) karein
		state.ui.modal.body.innerHTML = '';
	}

	/**
	 * (Confirmation Modal) ko kholta hai.
	 * @param {string} title (Title)
	 * @param {string} message Paigham
	 * @param {function} deleteCallback (Delete) (button) (click) hone par (callback)
	 */
	function openConfirmModal(title, message, deleteCallback) {
		if (!state.ui.confirmModal) return;

		state.ui.confirmModal.title.innerHTML = escapeHtml(
			title || rsamData.strings.confirmDelete
		);
		state.ui.confirmModal.body.querySelector('p').innerHTML = escapeHtml(
			message || rsamData.strings.confirmDelete
		);

		// (Delete) (listener) (bind) karein
		const newDeleteBtn = state.ui.confirmModal.deleteBtn.cloneNode(true);
		state.ui.confirmModal.deleteBtn.parentNode.replaceChild(
			newDeleteBtn,
			state.ui.confirmModal.deleteBtn
		);
		state.ui.confirmModal.deleteBtn = newDeleteBtn;
		state.ui.confirmModal.deleteBtn.addEventListener(
			'click',
			deleteCallback
		);

		document.body.classList.add('rsam-modal-open');
		state.ui.confirmModal.wrapper.classList.add('rsam-modal-visible');
	}

	/**
	 * (Confirmation Modal) ko (close) karta hai.
	 */
	function closeConfirmModal() {
		if (!state.ui.confirmModal) return;
		document.body.classList.remove('rsam-modal-open');
		state.ui.confirmModal.wrapper.classList.remove('rsam-modal-visible');
	}

	/**
	 * (Pagination) (controls) banata hai.
	 * @param {HTMLElement} container (Pagination) (container)
	 * @param {object} paginationData (PHP) se (pagination data)
	 * @param {function} callback (Page change) (callback)
	 */
	function renderPagination(container, paginationData, callback) {
		if (!container || !paginationData || paginationData.total_pages <= 1) {
			if (container) container.innerHTML = '';
			return;
		}

		const { current_page, total_pages } = paginationData;
		let html = '<div class="rsam-pagination-links">';

		// (Previous) (button)
		html += `<button type="button" class="button" data-page="${
			current_page - 1
		}" ${current_page === 1 ? 'disabled' : ''}>
            &laquo; ${rsamData.strings.prev || 'Prev'}
        </button>`;

		// (Page numbers) (Logic)
		// (Complex (pagination) (UI) yahan banaya ja sakta hai, abhi (simple) rakhte hain)
		html += `<span class="rsam-pagination-current">
            ${escapeHtml(
				`Page ${current_page} of ${total_pages}`
			)}
        </span>`;

		// (Next) (button)
		html += `<button type="button" class="button" data-page="${
			current_page + 1
		}" ${current_page === total_pages ? 'disabled' : ''}>
            ${rsamData.strings.next || 'Next'} &raquo;
        </button>`;

		html += '</div>';
		container.innerHTML = html;

		// (Listeners) (add) karein
		container.querySelectorAll('button[data-page]').forEach((button) => {
			button.addEventListener('click', (e) => {
				const newPage = parseInt(e.target.dataset.page, 10);
				if (newPage && newPage !== current_page) {
					callback(newPage);
				}
			});
		});
	}

	// (Aglay (Parts) yahan (append) honge)
	// ... (initDashboard, initProducts, etc.)
/**
	 * Part 2 — Dashboard Screen
	 * (Dashboard) (template) ko (mount) karta hai aur (stats) (load) karta hai.
	 */
	function initDashboard() {
		const tmpl = document.getElementById('rsam-tmpl-dashboard');
		if (!tmpl) {
			showError('Dashboard template not found.');
			return;
		}

		// (Template) ko (mount) karein
		const content = mountTemplate(tmpl);
		state.ui.root.innerHTML = ''; // (Loading placeholder) ko (remove) karein
		state.ui.root.appendChild(content);

		// (Stats) (load) karein
		fetchDashboardStats();
	}

	/**
	 * (AJAX) ke zariye (Dashboard) (widgets) ke liye (data) (fetch) karta hai.
	 */
	async function fetchDashboardStats() {
		const statsWidget = state.ui.root.querySelector(
			'.rsam-widget[data-widget="stats"]'
		);
		const topProductsWidget = state.ui.root.querySelector(
			'.rsam-widget[data-widget="top-products"]'
		);
		const lowStockWidget = state.ui.root.querySelector(
			'.rsam-widget[data-widget="low-stock"]'
		);

		try {
			const data = await wpAjax('rsam_get_dashboard_stats');

			// 1. (Overview Stats) (Widget)
			if (statsWidget) {
				statsWidget.classList.remove('rsam-widget-loading');
				statsWidget.querySelector('.rsam-widget-body').innerHTML = `
                    <div class="rsam-stats-grid">
                        <div class="rsam-stat-item">
                            <strong>${rsamData.strings.todaySales || 'Today\'s Sales'}</strong>
                            <span>${escapeHtml(data.today_sales)}</span>
                        </div>
                        <div class="rsam-stat-item">
                            <strong>${rsamData.strings.monthlySales || 'This Month\'s Sales'}</strong>
                            <span>${escapeHtml(data.monthly_sales)}</span>
                        </div>
                        <div class="rsam-stat-item rsam-stat-profit">
                            <strong>${rsamData.strings.monthlyProfit || 'This Month\'s Profit'}</strong>
                            <span>${escapeHtml(data.monthly_profit)}</span>
                        </div>
                        <div class="rsam-stat-item rsam-stat-expense">
                            <strong>${rsamData.strings.monthlyExpenses || 'This Month\'s Expenses'}</strong>
                            <span>${escapeHtml(data.monthly_expenses)}</span>
                        </div>
                        <div class="rsam-stat-item">
                            <strong>${rsamData.strings.stockValue || 'Total Stock Value'}</strong>
                            <span>${escapeHtml(data.stock_value)}</span>
                        </div>
                        <div class="rsam-stat-item rsam-stat-alert">
                            <strong>${rsamData.strings.lowStockItems || 'Low Stock Items'}</strong>
                            <span>${escapeHtml(data.low_stock_count)}</span>
                        </div>
                    </div>
                `;
			}

			// 2. (Top Selling Products) (Widget)
			if (topProductsWidget) {
				topProductsWidget.classList.remove('rsam-widget-loading');
				const body = topProductsWidget.querySelector('.rsam-widget-body');
				if (data.top_products && data.top_products.length > 0) {
					let listHtml = '<ul class="rsam-widget-list">';
					data.top_products.forEach((product) => {
						listHtml += `<li>
                            <span>${escapeHtml(product.name)}</span>
                            <strong>${escapeHtml(
								product.total_quantity
							)} ${rsamData.strings.unitsSold || 'units'}</strong>
                        </li>`;
					});
					listHtml += '</ul>';
					body.innerHTML = listHtml;
				} else {
					body.innerHTML = `<p>${rsamData.strings.noTopProducts || 'No top selling products this month.'}</p>`;
				}
			}

			// 3. (Low Stock) (Widget)
			if (lowStockWidget) {
				lowStockWidget.classList.remove('rsam-widget-loading');
				const body = lowStockWidget.querySelector('.rsam-widget-body');
				if (
					data.low_stock_products &&
					data.low_stock_products.length > 0
				) {
					let listHtml = '<ul class="rsam-widget-list rsam-low-stock-list">';
					data.low_stock_products.forEach((product) => {
						listHtml += `<li>
                            <span>${escapeHtml(product.name)}</span>
                            <strong>${rsamData.strings.inStock || 'In Stock:'} ${escapeHtml(
							product.stock_quantity
						)}</span>
                        </li>`;
					});
					listHtml += '</ul>';
					body.innerHTML = listHtml;
				} else {
					body.innerHTML = `<p>${rsamData.strings.allStockGood || 'All stock levels are good.'}</p>`;
				}
			}
		} catch (error) {
			// (Error) (Main stats widget) mein dikhayein
			if (statsWidget) {
				statsWidget.classList.remove('rsam-widget-loading');
				showError(error, statsWidget.querySelector('.rsam-widget-body'));
			}
			console.error('Failed to load dashboard stats:', error);
		}
	}

	/** Part 2 — Yahan khatam hua */
/**
	 * Part 3 — Products (Inventory) Screen
	 * (Products) (template) ko (mount) karta hai, (list) (fetch) karta hai, aur (CRUD) (handle) karta hai.
	 */
	function initProducts() {
		const tmpl = document.getElementById('rsam-tmpl-products');
		if (!tmpl) {
			showError('Products template not found.');
			return;
		}

		// (Template) ko (mount) karein
		const content = mountTemplate(tmpl);
		state.ui.root.innerHTML = ''; // (Loading placeholder) ko (remove) karein
		state.ui.root.appendChild(content);

		// (UI Elements) ko (cache) karein
		const ui = {
			tableBody: state.ui.root.querySelector('#rsam-products-table-body'),
			pagination: state.ui.root.querySelector(
				'#rsam-products-pagination'
			),
			search: state.ui.root.querySelector('#rsam-product-search'),
			addNewBtn: state.ui.root.querySelector('#rsam-add-new-product'),
			formContainer: state.ui.root.querySelector(
				'#rsam-product-form-container'
			),
		};
		// (UI) ko (state) mein (store) karein (event listeners) ke liye
		state.ui.products = ui;

		// (Initial) (Products) (fetch) karein
		fetchProducts();

		// (Event Listeners)
		// (Search)
		ui.search.addEventListener('keyup', (e) => {
			// (debounce) (timer)
			clearTimeout(state.searchTimer);
			state.searchTimer = setTimeout(() => {
				state.currentSearch = e.target.value;
				state.currentPage = 1; // (Search) par (page 1) par (reset) karein
				fetchProducts();
			}, 500); // 500ms (debounce)
		});

		// (Add New)
		ui.addNewBtn.addEventListener('click', () => {
			openProductForm();
		});
	}

	/**
	 * (AJAX) ke zariye (Products) (fetch) aur (render) karta hai.
	 */
	async function fetchProducts() {
		const { tableBody, pagination } = state.ui.products;
		if (!tableBody) return;

		// (Loading) (state)
		tableBody.innerHTML = `<tr>
            <td colspan="7" class="rsam-list-loading">
                <span class="rsam-loader-spinner"></span> ${rsamData.strings.loading}
            </td>
        </tr>`;

		try {
			const data = await wpAjax('rsam_get_products', {
				page: state.currentPage,
				search: state.currentSearch,
			});

			// (Table) (render) karein
			renderProductsTable(data.products);
			// (Pagination) (render) karein
			renderPagination(
				pagination,
				data.pagination,
				(newPage) => {
					state.currentPage = newPage;
					fetchProducts();
				}
			);
		} catch (error) {
			showError(error, tableBody);
		}
	}

	/**
	 * (Products) (data) ko (table) mein (render) karta hai.
	 * @param {Array} products (Products) ka (array)
	 */
	function renderProductsTable(products) {
		const { tableBody } = state.ui.products;
		tableBody.innerHTML = ''; // (Clear) karein

		if (!products || products.length === 0) {
			tableBody.innerHTML = `<tr>
                <td colspan="7" class="rsam-list-empty">
                    ${rsamData.strings.noItemsFound}
                </td>
            </tr>`;
			return;
		}

		products.forEach((product) => {
			const tr = document.createElement('tr');
			tr.dataset.productId = product.id;
			// (product) (object) ko (element) par (store) karein (edit) ke liye
			tr.dataset.productData = JSON.stringify(product);

			tr.innerHTML = `
                <td>${escapeHtml(product.name)}</td>
                <td>${escapeHtml(product.category)}</td>
                <td>${escapeHtml(product.unit_type)}</td>
                <td>${escapeHtml(
					product.stock_quantity
				)}</td> <td>${escapeHtml(product.stock_value_formatted)}</td>
                <td>${escapeHtml(product.selling_price_formatted)}</td>
                <td class="rsam-list-actions">
                    <button type="button" class="button rsam-edit-btn" title="${rsamData.strings.edit}">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                    <button type="button" class="button rsam-delete-btn" title="${rsamData.strings.delete}">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </td>
            `;

			// (Action Listeners)
			tr.querySelector('.rsam-edit-btn').addEventListener(
				'click',
				(e) => {
					const row = e.target.closest('tr');
					const data = JSON.parse(row.dataset.productData);
					openProductForm(data);
				}
			);

			tr.querySelector('.rsam-delete-btn').addEventListener(
				'click',
				(e) => {
					const row = e.target.closest('tr');
					const productId = row.dataset.productId;
					const productName = row.cells[0].textContent;
					confirmDeleteProduct(productId, productName);
				}
			);

			tableBody.appendChild(tr);
		});
	}

	/**
	 * (Add/Edit) (Product) (Form Modal) ko kholta hai.
	 * @param {object} [productData] (Edit) ke liye (Product) ka (data)
	 */
	function openProductForm(productData = null) {
		const { formContainer } = state.ui.products;
		const formHtml = formContainer.innerHTML; // (Form) (HTML) ko (template) se (copy) karein
		const isEditing = productData !== null;

		const title = isEditing
			? `${rsamData.strings.edit} Product`
			: `${rsamData.strings.addNew} Product`;

		// (Modal) kholne ke baad (form) ko (populate) karein
		openModal(title, formHtml, async (e) => {
			// (Save callback)
			const saveBtn = e.target;
			const form = state.ui.modal.body.querySelector('#rsam-product-form');
			if (form.checkValidity() === false) {
				form.reportValidity();
				return;
			}

			// (Form data) (serialize) karein
			const formData = new URLSearchParams(new FormData(form)).toString();

			try {
				const result = await wpAjax(
					'rsam_save_product',
					{ form_data: formData },
					saveBtn
				);
				showToast(result.message, 'success');
				closeModal();
				fetchProducts(); // (List) (refresh) karein
			} catch (error) {
				// (wpAjax) (toast) (show) kar dega
			}
		});

		// (Modal) khulne ke baad (form) ko (populate) karein
		if (isEditing) {
			const form = state.ui.modal.body.querySelector('#rsam-product-form');
			form.querySelector('[name="product_id"]').value = productData.id;
			form.querySelector('[name="name"]').value = productData.name;
			form.querySelector('[name="category"]').value = productData.category;
			form.querySelector('[name="unit_type"]').value =
				productData.unit_type;
			form.querySelector('[name="selling_price"]').value =
				productData.selling_price;
			form.querySelector('[name="low_stock_threshold"]').value =
				productData.low_stock_threshold;
			form.querySelector('[name="has_expiry"]').checked =
				!!Number(productData.has_expiry);
		}
	}

	/**
	 * (Product) ko (delete) karne ke liye (confirmation) (prompt) dikhata hai.
	 * @param {string|number} productId
	 * @param {string} productName
	 */
	function confirmDeleteProduct(productId, productName) {
		const title = `${rsamData.strings.delete} ${productName}?`;
		const message = `Are you sure you want to delete "${productName}"? This action cannot be undone.`;

		openConfirmModal(title, message, async (e) => {
			// (Delete callback)
			const deleteBtn = e.target;
			try {
				const result = await wpAjax(
					'rsam_delete_product',
					{ product_id: productId },
					deleteBtn
				);
				showToast(result.message, 'success');
				closeConfirmModal();
				fetchProducts(); // (List) (refresh) karein
			} catch (error) {
				// (wpAjax) (toast) (show) kar dega
				closeConfirmModal();
			}
		});
	}

	/** Part 3 — Yahan khatam hua */
  /**
	 * Part 4 — Purchases Screen
	 * (Purchases) (list) aur (form) (views) ko (handle) karta hai.
	 */
	function initPurchases() {
		const tmpl = document.getElementById('rsam-tmpl-purchases');
		if (!tmpl) {
			showError('Purchases template not found.');
			return;
		}

		// (Template) ko (mount) karein
		const content = mountTemplate(tmpl);
		state.ui.root.innerHTML = ''; // (Loading placeholder) ko (remove) karein
		state.ui.root.appendChild(content);

		// (UI Elements) ko (cache) karein
		const ui = {
			// (List View)
			listView: state.ui.root.querySelector('#rsam-purchase-list-view'),
			tableBody: state.ui.root.querySelector('#rsam-purchases-table-body'),
			pagination: state.ui.root.querySelector(
				'#rsam-purchases-pagination'
			),
			search: state.ui.root.querySelector('#rsam-purchase-search'),
			addNewBtn: state.ui.root.querySelector('#rsam-add-new-purchase'),

			// (Form View)
			formView: state.ui.root.querySelector('#rsam-purchase-form-view'),
			form: state.ui.root.querySelector('#rsam-purchase-form'),
			formTitle: state.ui.root.querySelector('#rsam-purchase-form-title'),
			backBtn: state.ui.root.querySelector('#rsam-back-to-purchase-list'),
			saveBtn: state.ui.root.querySelector('#rsam-save-purchase-form'),
			cancelBtn: state.ui.root.querySelector('#rsam-cancel-purchase-form'),

			// (Form Fields)
			productSearch: state.ui.root.querySelector(
				'#rsam-purchase-product-search'
			),
			itemsTableBody: state.ui.root.querySelector(
				'#rsam-purchase-items-body'
			),
			subtotalField: state.ui.root.querySelector(
				'#rsam-purchase-subtotal'
			),
			additionalCostsField: state.ui.root.querySelector(
				'#rsam-purchase-additional-costs'
			),
			totalAmountField: state.ui.root.querySelector(
				'#rsam-purchase-total-amount'
			),
			supplierSearch: state.ui.root.querySelector(
				'#rsam-purchase-supplier'
			),
			quickAddSupplierBtn: state.ui.root.querySelector(
				'.rsam-quick-add[data-type="supplier"]'
			),
		};
		// (UI) ko (state) mein (store) karein
		state.ui.purchases = ui;
		// (Items) ko (track) karne ke liye (array)
		state.purchaseItems = [];

		// (Initial) (Purchases) (fetch) karein
		fetchPurchases();

		// (Event Listeners)
		// (View Switching)
		ui.addNewBtn.addEventListener('click', () => {
			showPurchaseView('form');
		});
		ui.backBtn.addEventListener('click', () => {
			showPurchaseView('list');
		});
		ui.cancelBtn.addEventListener('click', () => {
			showPurchaseView('list');
		});

		// (List Search)
		ui.search.addEventListener('keyup', (e) => {
			clearTimeout(state.searchTimer);
			state.searchTimer = setTimeout(() => {
				state.currentSearch = e.target.value;
				state.currentPage = 1;
				fetchPurchases();
			}, 500);
		});

		// (Form Handling)
		setupProductAutocomplete(ui.productSearch, addProductToPurchase);
		initSupplierSearch(ui.supplierSearch, ui.quickAddSupplierBtn);

		// (Costs) (Calculation)
		ui.additionalCostsField.addEventListener('input', () => {
			calculatePurchaseTotals();
		});

		// (Save Purchase)
		ui.form.addEventListener('submit', (e) => {
			e.preventDefault();
			savePurchase();
		});
	}

	/**
	 * (Purchase List) aur (Form) ke darmiyan (view) (switch) karta hai.
	 * @param {'list'|'form'} view Dikhane wala (view)
	 * @param {object} [purchaseData] (Edit) ke liye (data) (abhi (unsupported) hai)
	 */
	function showPurchaseView(view, purchaseData = null) {
		const { listView, formView, form, formTitle } = state.ui.purchases;

		if (view === 'form') {
			listView.style.display = 'none';
			formView.style.display = 'block';

			// (Form) (Reset) karein
			form.reset();
			state.purchaseItems = [];
			renderPurchaseItems();
			calculatePurchaseTotals();

			if (purchaseData) {
				// (Edit) (Mode) - (Future implementation)
				formTitle.textContent =
					rsamData.strings.editPurchase || 'Edit Purchase';
				// (Form) (populate) karein
				// ...
			} else {
				// (New) (Mode)
				formTitle.textContent =
					rsamData.strings.newPurchase || 'Record New Purchase';
				// (Purchase date) ko (today) (set) karein
				form.querySelector('[name="purchase_date"]').value =
					new Date().toISOString().split('T')[0];
			}
		} else {
			// (List) (View)
			formView.style.display = 'none';
			listView.style.display = 'block';
		}
	}

	/**
	 * (AJAX) ke zariye (Purchases) (fetch) aur (render) karta hai.
	 */
	async function fetchPurchases() {
		const { tableBody, pagination } = state.ui.purchases;
		if (!tableBody) return;

		tableBody.innerHTML = `<tr>
            <td colspan="5" class="rsam-list-loading">
                <span class="rsam-loader-spinner"></span> ${rsamData.strings.loading}
            </td>
        </tr>`;

		try {
			const data = await wpAjax('rsam_get_purchases', {
				page: state.currentPage,
				search: state.currentSearch,
			});

			renderPurchasesTable(data.purchases);
			renderPagination(
				pagination,
				data.pagination,
				(newPage) => {
					state.currentPage = newPage;
					fetchPurchases();
				}
			);
		} catch (error) {
			showError(error, tableBody);
		}
	}

	/**
	 * (Purchases) (data) ko (table) mein (render) karta hai.
	 * @param {Array} purchases (Purchases) ka (array)
	 */
	function renderPurchasesTable(purchases) {
		const { tableBody } = state.ui.purchases;
		tableBody.innerHTML = '';

		if (!purchases || purchases.length === 0) {
			tableBody.innerHTML = `<tr>
                <td colspan="5" class="rsam-list-empty">
                    ${rsamData.strings.noItemsFound}
                </td>
            </tr>`;
			return;
		}

		purchases.forEach((purchase) => {
			const tr = document.createElement('tr');
			tr.dataset.purchaseId = purchase.id;
			tr.innerHTML = `
                <td>${escapeHtml(
					purchase.invoice_number || `Purchase #${purchase.id}`
				)}</td>
                <td>${escapeHtml(purchase.supplier_name)}</td>
                <td>${escapeHtml(purchase.purchase_date_formatted)}</td>
                <td>${escapeHtml(purchase.total_amount_formatted)}</td>
                <td class="rsam-list-actions">
                    <button type="button" class="button rsam-delete-btn" title="${rsamData.strings.delete}">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                    <button type="button" class="button rsam-edit-btn" title="${rsamData.strings.edit}" disabled>
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                </td>
            `;

			// (Delete) (Listener)
			tr.querySelector('.rsam-delete-btn').addEventListener(
				'click',
				(e) => {
					const row = e.target.closest('tr');
					const purchaseId = row.dataset.purchaseId;
					const purchaseName =
						row.cells[0].textContent || `Purchase #${purchaseId}`;
					confirmDeletePurchase(purchaseId, purchaseName);
				}
			);

			tableBody.appendChild(tr);
		});
	}

	/**
	 * (Purchase) ko (delete) karne ke liye (confirmation) (prompt) dikhata hai.
	 * @param {string|number} purchaseId
	 * @param {string} purchaseName
	 */
	function confirmDeletePurchase(purchaseId, purchaseName) {
		const title = `${rsamData.strings.delete} ${purchaseName}?`;
		const message = `Are you sure you want to delete "${purchaseName}"? This will reverse the stock quantities if they were not sold. This action cannot be undone.`;

		openConfirmModal(title, message, async (e) => {
			const deleteBtn = e.target;
			try {
				const result = await wpAjax(
					'rsam_delete_purchase',
					{ purchase_id: purchaseId },
					deleteBtn
				);
				showToast(result.message, 'success');
				closeConfirmModal();
				fetchPurchases(); // (List) (refresh) karein
			} catch (error) {
				// (wpAjax) (toast) (show) kar dega
				closeConfirmModal();
			}
		});
	}

	/**
	 * (Product Search) (Autocomplete) ko (setup) karta hai.
	 * @param {HTMLElement} inputEl (Input element)
	 * @param {function} callback (Product) (select) hone par (callback)
	 */
	function setupProductAutocomplete(inputEl, callback) {
		if (!window.jQuery.fn.autocomplete) {
			console.error('jQuery UI Autocomplete is not loaded.');
			return;
		}

		window
			.jQuery(inputEl)
			.autocomplete({
				source: (request, response) => {
					// (WordPress AJAX) (autocomplete) ke liye
					window
						.jQuery
						.get(rsamData.ajax_url, {
							action: 'rsam_search_products',
							nonce: rsamData.nonce,
							term: request.term,
						})
						.done((data) => {
							if (data.success) {
								response(data.data);
							} else {
								response([]);
							}
						});
				},
				minLength: 2,
				select: (event, ui) => {
					event.preventDefault(); // (Default) (value) (set) karne se rokein
					callback(ui.item.data); // (Pura product object) (callback) ko (pass) karein
					inputEl.value = ''; // (Input) (clear) karein
				},
			})
			.autocomplete('instance')._renderItem = (ul, item) => {
			// (Custom) (list item) (render) karein
			return window
				.jQuery('<li>')
				.append(`<div>${escapeHtml(item.label)}</div>`)
				.appendTo(ul);
		};
	}

	/**
	 * (Purchase form) mein (product) (add) karta hai.
	 * @param {object} product (Product) (data) (autocomplete se)
	 */
	function addProductToPurchase(product) {
		// Check karein ke (product) pehle se (list) mein to nahi
		const existingItem = state.purchaseItems.find(
			(item) => item.product_id === product.id
		);
		if (existingItem) {
			showToast('Product is already in the list.', 'warning');
			// (Quantity) (highlight) kar sakte hain
			const row = state.ui.purchases.itemsTableBody.querySelector(
				`tr[data-product-id="${product.id}"] input[name="quantity"]`
			);
			if (row) {
				row.focus();
				row.select();
			}
			return;
		}

		// Naya (item) (state) mein (add) karein
		state.purchaseItems.push({
			product_id: product.id,
			name: product.name,
			unit_type: product.unit_type,
			has_expiry: !!Number(product.has_expiry),
			quantity: 1,
			purchase_price: 0.0, // (User) (enter) karega
			expiry_date: '',
		});

		// (Table) (Re-render) karein
		renderPurchaseItems();
	}

	/**
	 * (Purchase form) mein (items) (table) ko (render) karta hai.
	 */
	function renderPurchaseItems() {
		const { itemsTableBody } = state.ui.purchases;
		itemsTableBody.innerHTML = ''; // (Clear) karein

		if (state.purchaseItems.length === 0) {
			itemsTableBody.innerHTML = `<tr class="rsam-no-items-row">
                <td colspan="6">${rsamData.strings.noItemsFound}</td>
            </tr>`;
			return;
		}

		state.purchaseItems.forEach((item, index) => {
			const tr = document.createElement('tr');
			tr.dataset.productId = item.product_id;
			tr.dataset.index = index;

			tr.innerHTML = `
                <td>${escapeHtml(item.name)} (${escapeHtml(item.unit_type)})</td>
                <td>
                    <input type="number" name="quantity" class="rsam-input-small" value="${escapeHtml(
						item.quantity
					)}" step="0.01" min="0.01" required>
                </td>
                <td>
                    <input type="number" name="purchase_price" class="rsam-input-small" value="${escapeHtml(
						item.purchase_price
					)}" step="0.01" min="0" required>
                </td>
                <td>
                    <input type="date" name="expiry_date" ${
						item.has_expiry ? '' : 'disabled'
					}>
                </td>
                <td class="rsam-item-subtotal">
                    ${formatPrice(item.quantity * item.purchase_price)}
                </td>
                <td>
                    <button type="button" class="button rsam-delete-btn rsam-item-remove" title="${rsamData.strings.delete}">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </td>
            `;

			// (Input) (change) (listeners) (state) ko (update) karne ke liye
			tr.querySelector('input[name="quantity"]').addEventListener(
				'input',
				(e) => {
					const newQty = parseFloat(e.target.value) || 0;
					state.purchaseItems[index].quantity = newQty;
					calculatePurchaseTotals(); // (Totals) (re-calculate) karein
					// (Row) (subtotal) (update) karein
					tr.querySelector('.rsam-item-subtotal').textContent =
						formatPrice(
							newQty * state.purchaseItems[index].purchase_price
						);
				}
			);
			tr.querySelector('input[name="purchase_price"]').addEventListener(
				'input',
				(e) => {
					const newPrice = parseFloat(e.target.value) || 0;
					state.purchaseItems[index].purchase_price = newPrice;
					calculatePurchaseTotals();
					tr.querySelector('.rsam-item-subtotal').textContent =
						formatPrice(
							newPrice * state.purchaseItems[index].quantity
						);
				}
			);
			tr.querySelector('input[name="expiry_date"]').addEventListener(
				'input',
				(e) => {
					state.purchaseItems[index].expiry_date = e.target.value;
				}
			);

			// (Remove) (button)
			tr.querySelector('.rsam-item-remove').addEventListener(
				'click',
				() => {
					state.purchaseItems.splice(index, 1); // (Array) se (remove) karein
					renderPurchaseItems(); // (List) (re-render) karein
					calculatePurchaseTotals();
				}
			);

			itemsTableBody.appendChild(tr);
		});
	}

	/**
	 * (Purchase form) mein (Subtotal) aur (Total Amount) (calculate) karta hai.
	 */
	function calculatePurchaseTotals() {
		const { subtotalField, additionalCostsField, totalAmountField } =
			state.ui.purchases;

		// (Items) (subtotal) (calculate) karein
		const subtotal = state.purchaseItems.reduce((total, item) => {
			return total + item.quantity * item.purchase_price;
		}, 0);

		const additionalCosts =
			parseFloat(additionalCostsField.value) || 0;
		const totalAmount = subtotal + additionalCosts;

		subtotalField.value = formatPrice(subtotal);
		totalAmountField.value = formatPrice(totalAmount);
	}

	/**
	 * Nayi (Purchase) ko (AJAX) ke zariye (save) karta hai.
	 */
	async function savePurchase() {
		const { form, saveBtn } = state.ui.purchases;

		if (form.checkValidity() === false) {
			form.reportValidity();
			showToast(rsamData.strings.invalidInput, 'error');
			return;
		}

		if (state.purchaseItems.length === 0) {
			showToast('Please add at least one product to the purchase.', 'error');
			return;
		}

		// (Form) (data) (object) banayein
		const formData = new FormData(form);
		const data = {};
		formData.forEach((value, key) => (data[key] = value));

		// (Items) ko (JSON string) mein (convert) karein
		data.items = JSON.stringify(state.purchaseItems);
		
		// (Prices) ko (unformat) karein (agar zaroorat ho, lekin hum (state) se le rahe hain)
		data.subtotal = state.purchaseItems.reduce(
			(total, item) => total + item.quantity * item.purchase_price,
			0
		);
		data.additional_costs =
			parseFloat(form.querySelector('[name="additional_costs"]').value) ||
			0;
		data.total_amount = data.subtotal + data.additional_costs;

		try {
			const result = await wpAjax('rsam_save_purchase', data, saveBtn);
			showToast(result.message, 'success');
			showPurchaseView('list'); // (List) (view) par wapis jayein
			fetchPurchases(); // (List) (refresh) karein
		} catch (error) {
			// (wpAjax) (toast) (show) kar dega
		}

  /**
	 * Part 5 — Sales (POS) Screen
	 * (Sales Ledger) (list) aur (New Sale) (form) (views) ko (handle) karta hai.
	 */
	function initSales() {
		const tmpl = document.getElementById('rsam-tmpl-sales');
		if (!tmpl) {
			showError('Sales template not found.');
			return;
		}

		// (Template) ko (mount) karein
		const content = mountTemplate(tmpl);
		state.ui.root.innerHTML = ''; // (Loading placeholder) ko (remove) karein
		state.ui.root.appendChild(content);

		// (UI Elements) ko (cache) karein
		const ui = {
			// (List View)
			listView: state.ui.root.querySelector('#rsam-sales-list-view'),
			tableBody: state.ui.root.querySelector('#rsam-sales-table-body'),
			pagination: state.ui.root.querySelector('#rsam-sales-pagination'),
			search: state.ui.root.querySelector('#rsam-sales-search'),
			addNewBtn: state.ui.root.querySelector('#rsam-add-new-sale'),

			// (Form View - POS)
			formView: state.ui.root.querySelector('#rsam-sales-form-view'),
			form: state.ui.root.querySelector('#rsam-sale-form'),
			formTitle: state.ui.root.querySelector('#rsam-sale-form-title'),
			backBtn: state.ui.root.querySelector('#rsam-back-to-sales-list'),
			saveBtn: state.ui.root.querySelector('#rsam-save-sale-form'),
			cancelBtn: state.ui.root.querySelector('#rsam-cancel-sale-form'),

			// (POS Fields)
			productSearch: state.ui.root.querySelector(
				'#rsam-sale-product-search'
			),
			itemsTableBody: state.ui.root.querySelector(
				'#rsam-sale-items-body'
			),
			customerSearch: state.ui.root.querySelector(
				'#rsam-sale-customer'
			),
			quickAddCustomerBtn: state.ui.root.querySelector(
				'.rsam-quick-add[data-type="customer"]'
			),
			stockAlert: state.ui.root.querySelector('#rsam-sale-stock-alert'),

			// (Summary)
			subtotalDisplay: state.ui.root.querySelector(
				'#rsam-sale-subtotal'
			),
			discountField: state.ui.root.querySelector('#rsam-sale-discount'),
			totalAmountDisplay: state.ui.root.querySelector(
				'#rsam-sale-total-amount'
			),
			paymentStatus: state.ui.root.querySelector(
				'#rsam-sale-payment-status'
			),
		};
		// (UI) ko (state) mein (store) karein
		state.ui.sales = ui;
		// (Cart) (Items) ko (track) karne ke liye (array)
		state.saleItems = [];

		// (Initial) (Sales) (fetch) karein
		fetchSales();

		// (Event Listeners)
		// (View Switching)
		ui.addNewBtn.addEventListener('click', () => {
			showSaleView('form');
		});
		ui.backBtn.addEventListener('click', () => {
			showSaleView('list');
		});
		ui.cancelBtn.addEventListener('click', () => {
			if (
				state.saleItems.length === 0 ||
				confirm('Are you sure you want to cancel this sale?')
			) {
				showSaleView('list');
			}
		});

		// (List Search)
		ui.search.addEventListener('keyup', (e) => {
			clearTimeout(state.searchTimer);
			state.searchTimer = setTimeout(() => {
				state.currentSearch = e.target.value;
				state.currentPage = 1;
				fetchSales();
			}, 500);
		});

		// (Form Handling)
		setupProductAutocomplete(ui.productSearch, addProductToSale);
		initCustomerSearch(ui.customerSearch, ui.quickAddCustomerBtn);

		// (Costs) (Calculation)
		ui.discountField.addEventListener('input', () => {
			calculateSaleTotals();
		});

		// (Save Sale)
		ui.saveBtn.addEventListener('click', (e) => {
			e.preventDefault();
			saveSale();
		});

		// (Unpaid) (sale) ke liye (customer) (check)
		ui.paymentStatus.addEventListener('change', (e) => {
			if (
				e.target.value === 'unpaid' &&
				parseInt(ui.customerSearch.value, 10) === 0
			) {
				showToast(
					'Please select a customer for Unpaid (Khata) sales.',
					'warning'
				);
				ui.customerSearch.focus();
			}
		});
	}

	/**
	 * (Sales List) aur (Form) ke darmiyan (view) (switch) karta hai.
	 * @param {'list'|'form'} view Dikhane wala (view)
	 */
	function showSaleView(view) {
		const { listView, formView, form } = state.ui.sales;

		if (view === 'form') {
			listView.style.display = 'none';
			formView.style.display = 'block';

			// (Form) (Reset) karein
			form.reset();
			state.saleItems = [];
			renderSaleItems();
			calculateSaleTotals();
			state.ui.sales.stockAlert.style.display = 'none';

			// (Product search) par (focus) karein
			state.ui.sales.productSearch.focus();
		} else {
			// (List) (View)
			formView.style.display = 'none';
			listView.style.display = 'block';
		}
	}

	/**
	 * (AJAX) ke zariye (Sales) (fetch) aur (render) karta hai.
	 */
	async function fetchSales() {
		const { tableBody, pagination } = state.ui.sales;
		if (!tableBody) return;

		tableBody.innerHTML = `<tr>
            <td colspan="7" class="rsam-list-loading">
                <span class="rsam-loader-spinner"></span> ${rsamData.strings.loading}
            </td>
        </tr>`;

		try {
			const data = await wpAjax('rsam_get_sales', {
				page: state.currentPage,
				search: state.currentSearch,
			});

			renderSalesTable(data.sales);
			renderPagination(
				pagination,
				data.pagination,
				(newPage) => {
					state.currentPage = newPage;
					fetchSales();
				}
			);
		} catch (error) {
			showError(error, tableBody);
		}
	}

	/**
	 * (Sales) (data) ko (table) mein (render) karta hai.
	 * @param {Array} sales (Sales) ka (array)
	 */
	function renderSalesTable(sales) {
		const { tableBody } = state.ui.sales;
		tableBody.innerHTML = '';

		if (!sales || sales.length === 0) {
			tableBody.innerHTML = `<tr>
                <td colspan="7" class="rsam-list-empty">
                    ${rsamData.strings.noItemsFound}
                </td>
            </tr>`;
			return;
		}

		sales.forEach((sale) => {
			const tr = document.createElement('tr');
			tr.dataset.saleId = sale.id;
			tr.innerHTML = `
                <td>${escapeHtml(sale.id)}</td>
                <td>${escapeHtml(sale.customer_name)}</td>
                <td>${escapeHtml(sale.sale_date_formatted)}</td>
                <td>${escapeHtml(sale.total_amount_formatted)}</td>
                <td>${escapeHtml(sale.total_profit_formatted)}</td>
                <td>
                    <span class="rsam-status rsam-status-${escapeHtml(
						sale.payment_status
					)}">
                        ${escapeHtml(sale.payment_status_label)}
                    </span>
                </td>
                <td class="rsam-list-actions">
                    <button type="button" class="button rsam-delete-btn" title="${rsamData.strings.delete}">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                    </td>
            `;

			// (Delete) (Listener)
			tr.querySelector('.rsam-delete-btn').addEventListener(
				'click',
				(e) => {
					const row = e.target.closest('tr');
					const saleId = row.dataset.saleId;
					confirmDeleteSale(saleId);
				}
			);

			tableBody.appendChild(tr);
		});
	}

	/**
	 * (Sale) ko (delete) karne ke liye (confirmation) (prompt) dikhata hai.
	 * @param {string|number} saleId
	 */
	function confirmDeleteSale(saleId) {
		const title = `${rsamData.strings.delete} Sale #${saleId}?`;
		const message = `Are you sure you want to delete Sale #${saleId}? This will reverse the stock and (Khata) balance. This action cannot be undone.`;

		openConfirmModal(title, message, async (e) => {
			const deleteBtn = e.target;
			try {
				const result = await wpAjax(
					'rsam_delete_sale',
					{ sale_id: saleId },
					deleteBtn
				);
				showToast(result.message, 'success');
				closeConfirmModal();
				fetchSales(); // (List) (refresh) karein
			} catch (error) {
				// (wpAjax) (toast) (show) kar dega
				closeConfirmModal();
			}
		});
	}

	/**
	 * (Customer) (Search) (Dropdown) ko (initialize) karta hai.
	 */
	function initCustomerSearch(selectEl, quickAddBtn) {
		// (Yeh (Select2) ya (Choices.js) jesi (library) istemal kar sakta hai)
		// (Filhal, hum (basic) (AJAX) (load) istemal karenge)

		async function loadCustomers() {
			try {
				const data = await wpAjax('rsam_get_customers', {
					limit: -1, // Tamam (customers)
				});
				selectEl.innerHTML =
					'<option value="0">Walk-in Customer</option>';
				data.customers.forEach((customer) => {
					const option = document.createElement('option');
					option.value = customer.id;
					option.textContent = escapeHtml(customer.name);
					selectEl.appendChild(option);
				});
			} catch (error) {
				console.error('Failed to load customers:', error);
			}
		}

		// (Quick Add) (Button)
		quickAddBtn.addEventListener('click', () => {
			// (Customer) (form) ko (modal) mein kholne ke liye (Part 12) ka (logic) (reuse) karein
			if (window.rsamOpenCustomerForm) {
				// (Callback) (function) (pass) karein
				window.rsamOpenCustomerForm(null, (newCustomer) => {
					// (Dropdown) mein naya (customer) (add) aur (select) karein
					const option = document.createElement('option');
					option.value = newCustomer.id;
					option.textContent = escapeHtml(newCustomer.name);
					option.selected = true;
					selectEl.appendChild(option);
				});
			} else {
				showToast(
					'Error: Customer form function not found.',
					'error'
				);
			}
		});

		loadCustomers(); // (Initial load)
	}

	/**
	 * (Sale) (cart) mein (product) (add) karta hai.
	 * @param {object} product (Product) (data) (autocomplete se)
	 */
	function addProductToSale(product) {
		const { stockAlert } = state.ui.sales;
		stockAlert.style.display = 'none'; // Purani (alert) (hide) karein

		// (Stock check)
		if (parseFloat(product.stock_quantity) <= 0) {
			stockAlert.textContent = `Error: "${product.name}" is out of stock.`;
			stockAlert.style.display = 'block';
			return;
		}

		// Check karein ke (product) pehle se (list) mein to nahi
		const existingItem = state.saleItems.find(
			(item) => item.product_id === product.id
		);

		if (existingItem) {
			// (Quantity) (increase) karein
			const newQty = existingItem.quantity + 1;
			// (Stock) (check)
			if (newQty > parseFloat(product.stock_quantity)) {
				stockAlert.textContent = `Error: Not enough stock for "${product.name}". Available: ${product.stock_quantity}.`;
				stockAlert.style.display = 'block';
				return;
			}
			existingItem.quantity = newQty;
		} else {
			// Naya (item) (state) mein (add) karein
			state.saleItems.push({
				product_id: product.id,
				name: product.name,
				unit_type: product.unit_type,
				quantity: 1,
				selling_price: parseFloat(product.selling_price),
				max_stock: parseFloat(product.stock_quantity),
			});
		}

		// (Table) (Re-render) karein
		renderSaleItems();
		calculateSaleTotals();
	}

	/**
	 * (Sale form) mein (items) (cart) ko (render) karta hai.
	 */
	function renderSaleItems() {
		const { itemsTableBody } = state.ui.sales;
		itemsTableBody.innerHTML = ''; // (Clear) karein

		if (state.saleItems.length === 0) {
			itemsTableBody.innerHTML = `<tr class="rsam-no-items-row">
                <td colspan="5">${rsamData.strings.cartEmpty || 'Cart is empty.'}</td>
            </tr>`;
			return;
		}

		state.saleItems.forEach((item, index) => {
			const tr = document.createElement('tr');
			tr.dataset.productId = item.product_id;
			tr.dataset.index = index;

			tr.innerHTML = `
                <td>${escapeHtml(item.name)}</td>
                <td>
                    <input type="number" name="quantity" class="rsam-input-small" value="${escapeHtml(
						item.quantity
					)}" step="1" min="1" max="${escapeHtml(item.max_stock)}">
                </td>
                <td>
                    <input type="number" name="selling_price" class="rsam-input-small" value="${escapeHtml(
						item.selling_price
					)}" step="0.01" min="0">
                </td>
                <td class="rsam-item-total">
                    ${formatPrice(item.quantity * item.selling_price)}
                </td>
                <td>
                    <button type="button" class="button rsam-delete-btn rsam-item-remove" title="${rsamData.strings.delete}">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </td>
            `;

			// (Input) (change) (listeners) (state) ko (update) karne ke liye
			tr.querySelector('input[name="quantity"]').addEventListener(
				'input',
				(e) => {
					const { stockAlert } = state.ui.sales;
					stockAlert.style.display = 'none';
					let newQty = parseFloat(e.target.value) || 0;

					if (newQty > item.max_stock) {
						newQty = item.max_stock;
						e.target.value = newQty;
						stockAlert.textContent = `Max stock available for "${item.name}" is ${item.max_stock}.`;
						stockAlert.style.display = 'block';
					}

					state.saleItems[index].quantity = newQty;
					calculateSaleTotals(); // (Totals) (re-calculate) karein
					// (Row) (total) (update) karein
					tr.querySelector('.rsam-item-total').textContent =
						formatPrice(
							newQty * state.saleItems[index].selling_price
						);
				}
			);
			tr.querySelector('input[name="selling_price"]').addEventListener(
				'input',
				(e) => {
					const newPrice = parseFloat(e.target.value) || 0;
					state.saleItems[index].selling_price = newPrice;
					calculateSaleTotals();
					tr.querySelector('.rsam-item-total').textContent =
						formatPrice(
							newPrice * state.saleItems[index].quantity
						);
				}
			);

			// (Remove) (button)
			tr.querySelector('.rsam-item-remove').addEventListener(
				'click',
				() => {
					state.saleItems.splice(index, 1); // (Array) se (remove) karein
					renderSaleItems(); // (List) (re-render) karein
					calculateSaleTotals();
				}
			);

			itemsTableBody.appendChild(tr);
		});
	}

	/**
	 * (Sale form) mein (Subtotal) aur (Total Amount) (calculate) karta hai.
	 */
	function calculateSaleTotals() {
		const { subtotalDisplay, discountField, totalAmountDisplay } =
			state.ui.sales;

		// (Items) (subtotal) (calculate) karein
		const subtotal = state.saleItems.reduce((total, item) => {
			return total + item.quantity * item.selling_price;
		}, 0);

		const discount = parseFloat(discountField.value) || 0;
		const totalAmount = subtotal - discount;

		subtotalDisplay.textContent = formatPrice(subtotal);
		totalAmountDisplay.textContent = formatPrice(
			totalAmount < 0 ? 0 : totalAmount
		);
	}

	/**
	 * Nayi (Sale) ko (AJAX) ke zariye (save) karta hai.
	 */
	async function saveSale() {
		const { form, saveBtn, customerSearch, paymentStatus } = state.ui.sales;

		if (state.saleItems.length === 0) {
			showToast('Cart is empty. Please add products to sell.', 'error');
			return;
		}

		const customerId = parseInt(customerSearch.value, 10) || 0;
		const payStatus = paymentStatus.value;

		if (payStatus === 'unpaid' && customerId === 0) {
			showToast(
				'Please select a customer to save an Unpaid (Khata) sale.',
				'error'
			);
			customerSearch.focus();
			return;
		}

		// (Form) (data) (object) banayein
		const formData = new FormData(form);
		const data = {};
		// (Form) se (fields) (extract) karein
		data.customer_id = customerId;
		data.payment_status = payStatus;
		data.notes = formData.get('notes');
		data.discount_amount =
			parseFloat(formData.get('discount_amount')) || 0;

		// (Items) ko (JSON string) mein (convert) karein
		// Sirf zaroori (data) bhejein
		const itemsToSave = state.saleItems.map((item) => ({
			product_id: item.product_id,
			quantity: item.quantity,
			selling_price: item.selling_price,
		}));
		data.items = JSON.stringify(itemsToSave);

		try {
			const result = await wpAjax('rsam_save_sale', data, saveBtn);
			showToast(result.message, 'success');
			showSaleView('list'); // (List) (view) par wapis jayein
			fetchSales(); // (List) (refresh) karein
			// (Future improvement): (Print receipt) (modal) dikhayein
		} catch (error) {
			// (wpAjax) (toast) (show) kar dega
			// Agar (stock) ka (error) (backend) se aaye
			if (error.includes('Insufficient stock')) {
				state.ui.sales.stockAlert.textContent = error;
				state.ui.sales.stockAlert.style.display = 'block';
			}
		}
	}

	/** Part 5 — Yahan khatam hua */
		/**
	 * Part 6 — Expenses Screen
	 * (Expenses) (template) ko (mount) karta hai, (list) (fetch) karta hai, aur (CRUD) (handle) karta hai.
	 */
	function initExpenses() {
		const tmpl = document.getElementById('rsam-tmpl-expenses');
		if (!tmpl) {
			showError('Expenses template not found.');
			return;
		}

		// (Template) ko (mount) karein
		const content = mountTemplate(tmpl);
		state.ui.root.innerHTML = ''; // (Loading placeholder) ko (remove) karein
		state.ui.root.appendChild(content);

		// (UI Elements) ko (cache) karein
		const ui = {
			tableBody: state.ui.root.querySelector('#rsam-expenses-table-body'),
			pagination: state.ui.root.querySelector(
				'#rsam-expenses-pagination'
			),
			search: state.ui.root.querySelector('#rsam-expense-search'),
			categoryFilter: state.ui.root.querySelector(
				'#rsam-expense-category-filter'
			),
			dateFilter: state.ui.root.querySelector(
				'#rsam-expense-date-filter'
			),
			addNewBtn: state.ui.root.querySelector('#rsam-add-new-expense'),
			formContainer: state.ui.root.querySelector(
				'#rsam-expense-form-container'
			),
		};
		// (UI) ko (state) mein (store) karein
		state.ui.expenses = ui;

		// (Initial) (Expenses) (fetch) karein
		fetchExpenses();

		// (Event Listeners)
		// (Filters)
		ui.search.addEventListener('keyup', (e) => {
			clearTimeout(state.searchTimer);
			state.searchTimer = setTimeout(() => {
				state.currentSearch = e.target.value;
				state.currentPage = 1;
				fetchExpenses();
			}, 500);
		});
		ui.categoryFilter.addEventListener('change', () => {
			state.currentPage = 1;
			fetchExpenses();
		});
		ui.dateFilter.addEventListener('change', () => {
			state.currentPage = 1;
			fetchExpenses();
		});

		// (Add New)
		ui.addNewBtn.addEventListener('click', () => {
			openExpenseForm();
		});
	}

	/**
	 * (AJAX) ke zariye (Expenses) (fetch) aur (render) karta hai.
	 */
	async function fetchExpenses() {
		const { tableBody, pagination, search, categoryFilter, dateFilter } =
			state.ui.expenses;
		if (!tableBody) return;

		// (Loading) (state)
		tableBody.innerHTML = `<tr>
            <td colspan="5" class="rsam-list-loading">
                <span class="rsam-loader-spinner"></span> ${rsamData.strings.loading}
            </td>
        </tr>`;

		try {
			const data = await wpAjax('rsam_get_expenses', {
				page: state.currentPage,
				search: search.value,
				category: categoryFilter.value,
				date: dateFilter.value,
			});

			// (Table) (render) karein
			renderExpensesTable(data.expenses);
			// (Pagination) (render) karein
			renderPagination(
				pagination,
				data.pagination,
				(newPage) => {
					state.currentPage = newPage;
					fetchExpenses();
				}
			);
		} catch (error) {
			showError(error, tableBody);
		}
	}

	/**
	 * (Expenses) (data) ko (table) mein (render) karta hai.
	 * @param {Array} expenses (Expenses) ka (array)
	 */
	function renderExpensesTable(expenses) {
		const { tableBody } = state.ui.expenses;
		tableBody.innerHTML = ''; // (Clear) karein

		if (!expenses || expenses.length === 0) {
			tableBody.innerHTML = `<tr>
                <td colspan="5" class="rsam-list-empty">
                    ${rsamData.strings.noItemsFound}
                </td>
            </tr>`;
			return;
		}

		expenses.forEach((expense) => {
			const tr = document.createElement('tr');
			tr.dataset.expenseId = expense.id;
			// (expense) (object) ko (element) par (store) karein (edit) ke liye
			tr.dataset.expenseData = JSON.stringify(expense);

			tr.innerHTML = `
                <td>${escapeHtml(expense.expense_date_formatted)}</td>
                <td>${escapeHtml(expense.category_label)}</td>
                <td>${escapeHtml(expense.amount_formatted)}</td>
                <td>${escapeHtml(expense.description)}</td>
                <td class="rsam-list-actions">
                    <button type="button" class="button rsam-edit-btn" title="${rsamData.strings.edit}">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                    <button type="button" class="button rsam-delete-btn" title="${rsamData.strings.delete}">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </td>
            `;

			// (Action Listeners)
			tr.querySelector('.rsam-edit-btn').addEventListener(
				'click',
				(e) => {
					const row = e.target.closest('tr');
					const data = JSON.parse(row.dataset.expenseData);
					openExpenseForm(data);
				}
			);

			tr.querySelector('.rsam-delete-btn').addEventListener(
				'click',
				(e) => {
					const row = e.target.closest('tr');
					const expenseId = row.dataset.expenseId;
					const expenseName = `${row.cells[1].textContent} - ${row.cells[2].textContent}`;
					confirmDeleteExpense(expenseId, expenseName);
				}
			);

			tableBody.appendChild(tr);
		});
	}

	/**
	 * (Add/Edit) (Expense) (Form Modal) ko kholta hai.
	 * @param {object} [expenseData] (Edit) ke liye (Expense) ka (data)
	 */
	function openExpenseForm(expenseData = null) {
		const { formContainer } = state.ui.expenses;
		const formHtml = formContainer.innerHTML; // (Form) (HTML) ko (template) se (copy) karein
		const isEditing = expenseData !== null;

		const title = isEditing
			? `${rsamData.strings.edit} Expense`
			: `${rsamData.strings.addNew} Expense`;

		// (Modal) kholne ke baad (form) ko (populate) karein
		openModal(title, formHtml, async (e) => {
			// (Save callback)
			const saveBtn = e.target;
			const form = state.ui.modal.body.querySelector('#rsam-expense-form');
			if (form.checkValidity() === false) {
				form.reportValidity();
				return;
			}

			// (Form data) (serialize) karein
			const formData = new URLSearchParams(new FormData(form)).toString();

			try {
				const result = await wpAjax(
					'rsam_save_expense',
					{ form_data: formData },
					saveBtn
				);
				showToast(result.message, 'success');
				closeModal();
				fetchExpenses(); // (List) (refresh) karein
			} catch (error) {
				// (wpAjax) (toast) (show) kar dega
			}
		});

		// (Modal) (elements) ko (find) karein
		const form = state.ui.modal.body.querySelector('#rsam-expense-form');
		const categorySelect = form.querySelector('#rsam-expense-category');
		const employeeField = form.querySelector('#rsam-expense-employee-field');
		const employeeSelect = form.querySelector(
			'#rsam-expense-employee-id'
		);

		// (Dynamic field) (Salary) (category) ke liye
		categorySelect.addEventListener('change', (e) => {
			if (e.target.value === 'salary') {
				employeeField.style.display = 'block';
				employeeSelect.required = true;
				loadEmployeesIntoSelect(employeeSelect); // (Employees) (load) karein
			} else {
				employeeField.style.display = 'none';
				employeeSelect.required = false;
			}
		});

		// (Modal) khulne ke baad (form) ko (populate) karein
		if (isEditing) {
			form.querySelector('[name="expense_id"]').value = expenseData.id;
			form.querySelector('[name="expense_date"]').value =
				expenseData.expense_date;
			form.querySelector('[name="amount"]').value = expenseData.amount;
			form.querySelector('[name="category"]').value =
				expenseData.category;
			form.querySelector('[name="description"]').value =
				expenseData.description;

			// (Salary) (category) (logic)
			if (expenseData.category === 'salary') {
				employeeField.style.display = 'block';
				employeeSelect.required = true;
				// (Employees) (load) karein aur (saved) (value) (select) karein
				loadEmployeesIntoSelect(
					employeeSelect,
					expenseData.employee_id
				);
			}
		} else {
			// (New) (form) mein (date) (default) (today) (set) karein
			form.querySelector('[name="expense_date"]').value =
				new Date().toISOString().split('T')[0];
		}
	}

	/**
	 * (Employees) ko (AJAX) se (load) karke (select) (dropdown) mein (populate) karta hai.
	 * @param {HTMLSelectElement} selectEl (Dropdown element)
	 * @param {string|number} [selectedValue] (Select) karne ke liye (ID)
	 */
	async function loadEmployeesIntoSelect(selectEl, selectedValue = null) {
		// (Check) karein agar pehle se (loaded) hain (sirf 'Select Employee' (option) ke alawa)
		if (selectEl.options.length > 1) {
			if (selectedValue) {
				selectEl.value = selectedValue;
			}
			return;
		}

		try {
			const data = await wpAjax('rsam_get_employees', {
				limit: -1, // Tamam (active) (employees) (backend ko yeh (handle) karna chahiye)
			});

			data.employees.forEach((emp) => {
				// Sirf (active) (employees) dikhayein
				if (Number(emp.is_active) === 1) {
					const option = document.createElement('option');
					option.value = emp.id;
					option.textContent = escapeHtml(emp.name);
					if (selectedValue && emp.id == selectedValue) {
						option.selected = true;
					}
					selectEl.appendChild(option);
				}
			});
		} catch (error) {
			console.error('Failed to load employees for dropdown:', error);
		}
	}

	/**
	 * (Expense) ko (delete) karne ke liye (confirmation)
/**
	 * Part 7 — Employees Screen
	 * (Employees) (template) ko (mount) karta hai, (list) (fetch) karta hai, aur (CRUD) (handle) karta hai.
	 */
	function initEmployees() {
		const tmpl = document.getElementById('rsam-tmpl-employees');
		if (!tmpl) {
			showError('Employees template not found.');
			return;
		}

		// (Template) ko (mount) karein
		const content = mountTemplate(tmpl);
		state.ui.root.innerHTML = ''; // (Loading placeholder) ko (remove) karein
		state.ui.root.appendChild(content);

		// (UI Elements) ko (cache) karein
		const ui = {
			tableBody: state.ui.root.querySelector(
				'#rsam-employees-table-body'
			),
			pagination: state.ui.root.querySelector(
				'#rsam-employees-pagination'
			),
			search: state.ui.root.querySelector('#rsam-employee-search'),
			addNewBtn: state.ui.root.querySelector('#rsam-add-new-employee'),
			formContainer: state.ui.root.querySelector(
				'#rsam-employee-form-container'
			),
		};
		// (UI) ko (state) mein (store) karein
		state.ui.employees = ui;

		// (Initial) (Employees) (fetch) karein
		fetchEmployees();

		// (Event Listeners)
		// (Search)
		ui.search.addEventListener('keyup', (e) => {
			clearTimeout(state.searchTimer);
			state.searchTimer = setTimeout(() => {
				state.currentSearch = e.target.value;
				state.currentPage = 1;
				fetchEmployees();
			}, 500);
		});

		// (Add New)
		ui.addNewBtn.addEventListener('click', () => {
			openEmployeeForm();
		});
	}

	/**
	 * (AJAX) ke zariye (Employees) (fetch) aur (render) karta hai.
	 */
	async function fetchEmployees() {
		const { tableBody, pagination, search } = state.ui.employees;
		if (!tableBody) return;

		// (Loading) (state)
		tableBody.innerHTML = `<tr>
            <td colspan="6" class="rsam-list-loading">
                <span class="rsam-loader-spinner"></span> ${rsamData.strings.loading}
            </td>
        </tr>`;

		try {
			const data = await wpAjax('rsam_get_employees', {
				page: state.currentPage,
				search: search.value,
			});

			// (Table) (render) karein
			renderEmployeesTable(data.employees);
			// (Pagination) (render) karein
			renderPagination(
				pagination,
				data.pagination,
				(newPage) => {
					state.currentPage = newPage;
					fetchEmployees();
				}
			);
		} catch (error) {
			showError(error, tableBody);
		}
	}

	/**
	 * (Employees) (data) ko (table) mein (render) karta hai.
	 * @param {Array} employees (Employees) ka (array)
	 */
	function renderEmployeesTable(employees) {
		const { tableBody } = state.ui.employees;
		tableBody.innerHTML = ''; // (Clear) karein

		if (!employees || employees.length === 0) {
			tableBody.innerHTML = `<tr>
                <td colspan="6" class="rsam-list-empty">
                    ${rsamData.strings.noItemsFound}
                </td>
            </tr>`;
			return;
		}

		employees.forEach((employee) => {
			const tr = document.createElement('tr');
			tr.dataset.employeeId = employee.id;
			// (employee) (object) ko (element) par (store) karein (edit) ke liye
			tr.dataset.employeeData = JSON.stringify(employee);

			tr.innerHTML = `
                <td>${escapeHtml(employee.name)}</td>
                <td>${escapeHtml(employee.designation)}</td>
                <td>${escapeHtml(employee.phone)}</td>
                <td>${escapeHtml(employee.monthly_salary_formatted)}</td>
                <td>
                    <span class="rsam-status ${
						Number(employee.is_active)
							? 'rsam-status-active'
							: 'rsam-status-inactive'
					}">
                        ${escapeHtml(employee.status_label)}
                    </span>
                </td>
                <td class="rsam-list-actions">
                    <button type="button" class="button rsam-edit-btn" title="${rsamData.strings.edit}">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                    <button type="button" class="button rsam-delete-btn" title="${rsamData.strings.delete}">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </td>
            `;

			// (Action Listeners)
			tr.querySelector('.rsam-edit-btn').addEventListener(
				'click',
				(e) => {
					const row = e.target.closest('tr');
					const data = JSON.parse(row.dataset.employeeData);
					openEmployeeForm(data);
				}
			);

			tr.querySelector('.rsam-delete-btn').addEventListener(
				'click',
				(e) => {
					const row = e.target.closest('tr');
					const employeeId = row.dataset.employeeId;
					const employeeName = row.cells[0].textContent;
					confirmDeleteEmployee(employeeId, employeeName);
				}
			);

			tableBody.appendChild(tr);
		});
	}

	/**
	 * (Add/Edit) (Employee) (Form Modal) ko kholta hai.
	 * @param {object} [employeeData] (Edit) ke liye (Employee) ka (data)
	 */
	function openEmployeeForm(employeeData = null) {
		const { formContainer } = state.ui.employees;
		const formHtml = formContainer.innerHTML; // (Form) (HTML) ko (template) se (copy) karein
		const isEditing = employeeData !== null;

		const title = isEditing
			? `${rsamData.strings.edit} Employee`
			: `${rsamData.strings.addNew} Employee`;

		// (Modal) kholne ke baad (form) ko (populate) karein
		openModal(title, formHtml, async (e) => {
			// (Save callback)
			const saveBtn = e.target;
			const form =
				state.ui.modal.body.querySelector('#rsam-employee-form');
			if (form.checkValidity() === false) {
				form.reportValidity();
				return;
			}

			// (Form data) (serialize) karein
			const formData = new URLSearchParams(new FormData(form)).toString();

			try {
				const result = await wpAjax(
					'rsam_save_employee',
					{ form_data: formData },
					saveBtn
				);
				showToast(result.message, 'success');
				closeModal();
				fetchEmployees(); // (List) (refresh) karein
			} catch (error) {
				// (wpAjax) (toast) (show) kar dega
			}
		});

		// (Modal) khulne ke baad (form) ko (populate) karein
		if (isEditing) {
			const form =
				state.ui.modal.body.querySelector('#rsam-employee-form');
			form.querySelector('[name="employee_id"]').value = employeeData.id;
			form.querySelector('[name="name"]').value = employeeData.name;
			form.querySelector('[name="designation"]').value =
				employeeData.designation;
			form.querySelector('[name="phone"]').value = employeeData.phone;
			form.querySelector('[name="monthly_salary"]').value =
				employeeData.monthly_salary;
			form.querySelector('[name="joining_date"]').value =
				employeeData.joining_date;
			form.querySelector('[name="is_active"]').checked =
				!!Number(employeeData.is_active);
		}
	}

	/**
	 * (Employee) ko (delete) karne ke liye (confirmation) (prompt) dikhata hai.
	 * @param {string|number} employeeId
	 * @param {string} employeeName
	 */
	function confirmDeleteEmployee(employeeId, employeeName) {
		const title = `${rsamData.strings.delete} ${employeeName}?`;
		const message = `Are you sure you want to delete "${employeeName}"? If this employee has salary records, deletion might fail.`;

		openConfirmModal(title, message, async (e) => {
			// (Delete callback)
			const deleteBtn = e.target;
			try {
				const result = await wpAjax(
					'rsam_delete_employee',
					{ employee_id: employeeId },
					deleteBtn
				);
				showToast(result.message, 'success');
				closeConfirmModal();
				fetchEmployees(); // (List) (refresh) karein
			} catch (error) {
				// (wpAjax) (toast) (show) kar dega
				closeConfirmModal();
			}
		});
	}

	/** Part 7 — Yahan khatam hua */
/**
	 * Part 8 — Suppliers Screen
	 * (Suppliers) (template) ko (mount) karta hai, (list) (fetch) karta hai, aur (CRUD) (handle) karta hai.
	 * (Yeh (Purchases) screen se (Quick Add) ke liye bhi istemal hota hai)
	 */
	function initSuppliers() {
		const tmpl = document.getElementById('rsam-tmpl-suppliers');
		if (!tmpl) {
			showError('Suppliers template not found.');
			return;
		}

		// (Template) ko (mount) karein
		const content = mountTemplate(tmpl);
		state.ui.root.innerHTML = ''; // (Loading placeholder) ko (remove) karein
		state.ui.root.appendChild(content);

		// (UI Elements) ko (cache) karein
		const ui = {
			tableBody: state.ui.root.querySelector(
				'#rsam-suppliers-table-body'
			),
			pagination: state.ui.root.querySelector(
				'#rsam-suppliers-pagination'
			),
			search: state.ui.root.querySelector('#rsam-supplier-search'),
			addNewBtn: state.ui.root.querySelector('#rsam-add-new-supplier'),
			formContainer: state.ui.root.querySelector(
				'#rsam-supplier-form-container'
			),
		};
		// (UI) ko (state) mein (store) karein
		state.ui.suppliers = ui;

		// (Initial) (Suppliers) (fetch) karein
		fetchSuppliers();

		// (Event Listeners)
		// (Search)
		ui.search.addEventListener('keyup', (e) => {
			clearTimeout(state.searchTimer);
			state.searchTimer = setTimeout(() => {
				state.currentSearch = e.target.value;
				state.currentPage = 1;
				fetchSuppliers();
			}, 500);
		});

		// (Add New)
		ui.addNewBtn.addEventListener('click', () => {
			// (Callback) (function) (pass) karein (list) ko (refresh) karne ke liye
			openSupplierForm(null, () => {
				fetchSuppliers();
			});
		});
	}

	/**
	 * (AJAX) ke zariye (Suppliers) (fetch) aur (render) karta hai.
	 */
	async function fetchSuppliers() {
		const { tableBody, pagination, search } = state.ui.suppliers;
		if (!tableBody) return;

		// (Loading) (state)
		tableBody.innerHTML = `<tr>
            <td colspan="4" class="rsam-list-loading">
                <span class="rsam-loader-spinner"></span> ${rsamData.strings.loading}
            </td>
        </tr>`;

		try {
			const data = await wpAjax('rsam_get_suppliers', {
				page: state.currentPage,
				search: search.value,
			});

			// (Table) (render) karein
			renderSuppliersTable(data.suppliers);
			// (Pagination) (render) karein
			renderPagination(
				pagination,
				data.pagination,
				(newPage) => {
					state.currentPage = newPage;
					fetchSuppliers();
				}
			);
		} catch (error) {
			showError(error, tableBody);
		}
	}

	/**
	 * (Suppliers) (data) ko (table) mein (render) karta hai.
	 * @param {Array} suppliers (Suppliers) ka (array)
	 */
	function renderSuppliersTable(suppliers) {
		const { tableBody } = state.ui.suppliers;
		tableBody.innerHTML = ''; // (Clear) karein

		if (!suppliers || suppliers.length === 0) {
			tableBody.innerHTML = `<tr>
                <td colspan="4" class="rsam-list-empty">
                    ${rsamData.strings.noItemsFound}
                </td>
            </tr>`;
			return;
		}

		suppliers.forEach((supplier) => {
			const tr = document.createElement('tr');
			tr.dataset.supplierId = supplier.id;
			// (supplier) (object) ko (element) par (store) karein (edit) ke liye
			tr.dataset.supplierData = JSON.stringify(supplier);

			tr.innerHTML = `
                <td>${escapeHtml(supplier.name)}</td>
                <td>${escapeHtml(supplier.phone)}</td>
                <td>${escapeHtml(supplier.address)}</td>
                <td class="rsam-list-actions">
                    <button type="button" class="button rsam-edit-btn" title="${rsamData.strings.edit}">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                    <button type="button" class="button rsam-delete-btn" title="${rsamData.strings.delete}">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </td>
            `;

			// (Action Listeners)
			tr.querySelector('.rsam-edit-btn').addEventListener(
				'click',
				(e) => {
					const row = e.target.closest('tr');
					const data = JSON.parse(row.dataset.supplierData);
					// (List) (refresh) (callback)
					openSupplierForm(data, () => {
						fetchSuppliers();
					});
				}
			);

			tr.querySelector('.rsam-delete-btn').addEventListener(
				'click',
				(e) => {
					const row = e.target.closest('tr');
					const supplierId = row.dataset.supplierId;
					const supplierName = row.cells[0].textContent;
					confirmDeleteSupplier(supplierId, supplierName);
				}
			);

			tableBody.appendChild(tr);
		});
	}

	/**
	 * (Add/Edit) (Supplier) (Form Modal) ko kholta hai.
	 * (Isey (global) (window object) par (attach) karein taake (Purchases) screen isey (call) kar sake)
	 * @param {object} [supplierData] (Edit) ke liye (Supplier) ka (data)
	 * @param {function} [onSaveCallback] (Save) hone ke baad (callback) (jo (list refresh) karega ya (dropdown update) karega)
	 */
	function openSupplierForm(supplierData = null, onSaveCallback = null) {
		// (Form container) (Suppliers) (template) ya (Customers) (template) se (find) karein
		// (Yeh (logic) (refactor) kiya ja sakta hai, lekin (Purchases) screen mein (supplier template) nahi hai)
		// Hum (Suppliers) (template) par (depend) karenge
		const formContainer = document.getElementById(
			'rsam-supplier-form-container'
		);
		if (!formContainer) {
			showToast('Supplier form template not found.', 'error');
			return;
		}

		const formHtml = formContainer.innerHTML;
		const isEditing = supplierData !== null;

		const title = isEditing
			? `${rsamData.strings.edit} Supplier`
			: `${rsamData.strings.addNew} Supplier`;

		openModal(title, formHtml, async (e) => {
			// (Save callback)
			const saveBtn = e.target;
			const form =
				state.ui.modal.body.querySelector('#rsam-supplier-form');
			if (form.checkValidity() === false) {
				form.reportValidity();
				return;
			}

			const formData = new URLSearchParams(new FormData(form)).toString();

			try {
				const result = await wpAjax(
					'rsam_save_supplier',
					{ form_data: formData },
					saveBtn
				);
				showToast(result.message, 'success');
				closeModal();
				// (Callback) (run) karein (agar (defined) hai)
				if (onSaveCallback) {
					onSaveCallback(result.supplier); // (Naya/updated) (supplier object) (pass) karein
				}
			} catch (error) {
				// (wpAjax) (toast) (show) kar dega
			}
		});

		// (Modal) khulne ke baad (form) ko (populate) karein
		if (isEditing) {
			const form =
				state.ui.modal.body.querySelector('#rsam-supplier-form');
			form.querySelector('[name="supplier_id"]').value = supplierData.id;
			form.querySelector('[name="name"]').value = supplierData.name;
			form.querySelector('[name="phone"]').value = supplierData.phone;
			form.querySelector('[name="address"]').value = supplierData.address;
		}
	}
	// (Global) (function) (expose) karein (Purchases) (screen) ke liye
	window.rsamOpenSupplierForm = openSupplierForm;

	/**
	 * (Supplier) ko (delete) karne ke liye (confirmation) (prompt) dikhata hai.
	 * @param {string|number} supplierId
	 * @param {string} supplierName
	 */
	function confirmDeleteSupplier(supplierId, supplierName) {
		const title = `${rsamData.strings.delete} ${supplierName}?`;
		const message = `Are you sure you want to delete "${supplierName}"? If this supplier is linked to purchases, deletion will fail.`;

		openConfirmModal(title, message, async (e) => {
			// (Delete callback)
			const deleteBtn = e.target;
			try {
				const result = await wpAjax(
					'rsam_delete_supplier',
					{ supplier_id: supplierId },
					deleteBtn
				);
				showToast(result.message, 'success');
				closeConfirmModal();
				fetchSuppliers(); // (List) (refresh) karein
			} catch (error) {
				// (wpAjax) (toast) (show) kar dega
				closeConfirmModal();
			}
		});
	}

	/** Part 8 — Yahan khatam hua */

/**
	 * Part 9 — Customers (Khata) Screen
	 * (Customers) (template) ko (mount) karta hai, (list) (fetch) karta hai,
	 * (CRUD) (handle) karta hai, aur (Payments) (record) karta hai.
	 */
	function initCustomers() {
		const tmpl = document.getElementById('rsam-tmpl-customers');
		if (!tmpl) {
			showError('Customers template not found.');
			return;
		}

		// (Template) ko (mount) karein
		const content = mountTemplate(tmpl);
		state.ui.root.innerHTML = ''; // (Loading placeholder) ko (remove) karein
		state.ui.root.appendChild(content);

		// (UI Elements) ko (cache) karein
		const ui = {
			tableBody: state.ui.root.querySelector(
				'#rsam-customers-table-body'
			),
			pagination: state.ui.root.querySelector(
				'#rsam-customers-pagination'
			),
			search: state.ui.root.querySelector('#rsam-customer-search'),
			addNewBtn: state.ui.root.querySelector('#rsam-add-new-customer'),
			formContainer: state.ui.root.querySelector(
				'#rsam-customer-form-container'
			),
			paymentFormContainer: state.ui.root.querySelector(
				'#rsam-customer-payment-form-container'
			),
		};
		// (UI) ko (state) mein (store) karein
		state.ui.customers = ui;

		// (Initial) (Customers) (fetch) karein
		fetchCustomers();

		// (Event Listeners)
		// (Search)
		ui.search.addEventListener('keyup', (e) => {
			clearTimeout(state.searchTimer);
			state.searchTimer = setTimeout(() => {
				state.currentSearch = e.target.value;
				state.currentPage = 1;
				fetchCustomers();
			}, 500);
		});

		// (Add New)
		ui.addNewBtn.addEventListener('click', () => {
			// (Callback) (function) (pass) karein (list) ko (refresh) karne ke liye
			openCustomerForm(null, () => {
				fetchCustomers();
			});
		});
	}

	/**
	 * (AJAX) ke zariye (Customers) (fetch) aur (render) karta hai.
	 */
	async function fetchCustomers() {
		const { tableBody, pagination, search } = state.ui.customers;
		if (!tableBody) return;

		// (Loading) (state)
		tableBody.innerHTML = `<tr>
            <td colspan="4" class="rsam-list-loading">
                <span class="rsam-loader-spinner"></span> ${rsamData.strings.loading}
            </td>
        </tr>`;

		try {
			const data = await wpAjax('rsam_get_customers', {
				page: state.currentPage,
				search: search.value,
			});

			// (Table) (render) karein
			renderCustomersTable(data.customers);
			// (Pagination) (render) karein
			renderPagination(
				pagination,
				data.pagination,
				(newPage) => {
					state.currentPage = newPage;
					fetchCustomers();
				}
			);
		} catch (error) {
			showError(error, tableBody);
		}
	}

	/**
	 * (Customers) (data) ko (table) mein (render) karta hai.
	 * @param {Array} customers (Customers) ka (array)
	 */
	function renderCustomersTable(customers) {
		const { tableBody } = state.ui.customers;
		tableBody.innerHTML = ''; // (Clear) karein

		if (!customers || customers.length === 0) {
			tableBody.innerHTML = `<tr>
                <td colspan="4" class="rsam-list-empty">
                    ${rsamData.strings.noItemsFound}
                </td>
            </tr>`;
			return;
		}

		customers.forEach((customer) => {
			const tr = document.createElement('tr');
			tr.dataset.customerId = customer.id;
			// (customer) (object) ko (element) par (store) karein (edit) ke liye
			tr.dataset.customerData = JSON.stringify(customer);

			tr.innerHTML = `
                <td>${escapeHtml(customer.name)}</td>
                <td>${escapeHtml(customer.phone)}</td>
                <td>${escapeHtml(customer.credit_balance_formatted)}</td>
                <td class="rsam-list-actions">
                    <button type="button" class="button button-primary rsam-payment-btn" title="${rsamData.strings.recordPayment || 'Record Payment'}">
                        <span class="dashicons dashicons-money-alt"></span>
                    </button>
                    <button type="button" class="button rsam-edit-btn" title="${rsamData.strings.edit}">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                    <button type="button" class="button rsam-delete-btn" title="${rsamData.strings.delete}">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </td>
            `;

			// (Action Listeners)
			tr.querySelector('.rsam-payment-btn').addEventListener(
				'click',
				(e) => {
					const row = e.target.closest('tr');
					const data = JSON.parse(row.dataset.customerData);
					openCustomerPaymentForm(data);
				}
			);

			tr.querySelector('.rsam-edit-btn').addEventListener(
				'click',
				(e) => {
					const row = e.target.closest('tr');
					const data = JSON.parse(row.dataset.customerData);
					// (List) (refresh) (callback)
					openCustomerForm(data, () => {
						fetchCustomers();
					});
				}
			);

			tr.querySelector('.rsam-delete-btn').addEventListener(
				'click',
				(e) => {
					const row = e.target.closest('tr');
					const customerId = row.dataset.customerId;
					const customerName = row.cells[0].textContent;
					confirmDeleteCustomer(customerId, customerName);
				}
			);

			tableBody.appendChild(tr);
		});
	}

	/**
	 * (Add/Edit) (Customer) (Form Modal) ko kholta hai.
	 * (Isey (global) (window object) par (attach) karein taake (Sales) screen isey (call) kar sake)
	 * @param {object} [customerData] (Edit) ke liye (Customer) ka (data)
	 * @param {function} [onSaveCallback] (Save) hone ke baad (callback)
	 */
	function openCustomerForm(customerData = null, onSaveCallback = null) {
		const formContainer = document.getElementById(
			'rsam-customer-form-container'
		);
		if (!formContainer) {
			showToast('Customer form template not found.', 'error');
			return;
		}

		const formHtml = formContainer.innerHTML;
		const isEditing = customerData !== null;

		const title = isEditing
			? `${rsamData.strings.edit} Customer`
			: `${rsamData.strings.addNew} Customer`;

		openModal(title, formHtml, async (e) => {
			// (Save callback)
			const saveBtn = e.target;
			const form =
				state.ui.modal.body.querySelector('#rsam-customer-form');
			if (form.checkValidity() === false) {
				form.reportValidity();
				return;
			}

			const formData = new URLSearchParams(new FormData(form)).toString();

			try {
				const result = await wpAjax(
					'rsam_save_customer',
					{ form_data: formData },
					saveBtn
				);
				showToast(result.message, 'success');
				closeModal();
				// (Callback) (run) karein (agar (defined) hai)
				if (onSaveCallback) {
					onSaveCallback(result.customer); // (Naya/updated) (customer object) (pass) karein
				}
			} catch (error) {
				// (wpAjax) (toast) (show) kar dega
			}
		});

		// (Modal) khulne ke baad (form) ko (populate) karein
		if (isEditing) {
			const form =
				state.ui.modal.body.querySelector('#rsam-customer-form');
			form.querySelector('[name="customer_id"]').value = customerData.id;
			form.querySelector('[name="name"]').value = customerData.name;
			form.querySelector('[name="phone"]').value = customerData.phone;
			form.querySelector('[name="address"]').value = customerData.address;
		}
	}
	// (Global) (function) (expose) karein (Sales) (screen) ke liye
	window.rsamOpenCustomerForm = openCustomerForm;

	/**
	 * (Customer) ko (delete) karne ke liye (confirmation) (prompt) dikhata hai.
	 * @param {string|number} customerId
	 * @param {string} customerName
	 */
	function confirmDeleteCustomer(customerId, customerName) {
		const title = `${rsamData.strings.delete} ${customerName}?`;
		const message = `Are you sure you want to delete "${customerName}"? If this customer has a balance or sales history, deletion will fail.`;

		openConfirmModal(title, message, async (e) => {
			// (Delete callback)
			const deleteBtn = e.target;
			try {
				const result = await wpAjax(
					'rsam_delete_customer',
					{ customer_id: customerId },
					deleteBtn
				);
				showToast(result.message, 'success');
				closeConfirmModal();
				fetchCustomers(); // (List) (refresh) karein
			} catch (error) {
				// (wpAjax) (toast) (show) kar dega
				closeConfirmModal();
			}
		});
	}

	/**
	 * (Customer Payment) (Form Modal) ko kholta hai.
	 * @param {object} customerData (Customer) ka (data)
	 */
	function openCustomerPaymentForm(customerData) {
		const { paymentFormContainer } = state.ui.customers;
		const formHtml = paymentFormContainer.innerHTML;
		const title =
			`${rsamData.strings.recordPayment || 'Record Payment'} for ${customerData.name}`;

		openModal(title, formHtml, async (e) => {
			// (Save callback)
			const saveBtn = e.target;
			const form = state.ui.modal.body.querySelector(
				'#rsam-customer-payment-form'
			);
			if (form.checkValidity() === false) {
				form.reportValidity();
				return;
			}

			const formData = new URLSearchParams(new FormData(form)).toString();

			try {
				const result = await wpAjax(
					'rsam_record_customer_payment',
					{ form_data: formData },
					saveBtn
				);
				showToast(result.message, 'success');
				closeModal();
				fetchCustomers(); // (List) (refresh) karein
			} catch (error) {
				// (wpAjax) (toast) (show) kar dega
			}
		});

		// (Modal) (form) ko (populate) karein
		const form = state.ui.modal.body.querySelector(
			'#rsam-customer-payment-form'
		);
		form.querySelector('[name="customer_id"]').value = customerData.id;
		form.querySelector(
			'.rsam-payment-customer-name'
		).textContent = `Customer: ${customerData.name}`;
		form.querySelector(
			'.rsam-payment-current-balance'
		).textContent = `Current Dues: ${customerData.credit_balance_formatted}`;

		// (Amount) (field) mein (balance) (default) daal dein (agar 0 se zyada hai)
		const amountInput = form.querySelector('#rsam-payment-amount');
		const balance = parseFloat(customerData.credit_balance);
		if (balance > 0) {
			amountInput.value = balance;
			amountInput.max = balance;
		}

		// (Payment date) (today) (set) karein
		form.querySelector('#rsam-payment-date').value =
			new Date().toISOString().split('T')[0];
	}

	/** Part 9 — Yahan khatam hua */

		/**
	 * Part 10 — Reports Screen
	 * (Reports) (form) ko (handle) karta hai aur (results) ko (render) karta hai.
	 */
	function initReports() {
		const tmpl = document.getElementById('rsam-tmpl-reports');
		if (!tmpl) {
			showError('Reports template not found.');
			return;
		}

		// (Template) ko (mount) karein
		const content = mountTemplate(tmpl);
		state.ui.root.innerHTML = ''; // (Loading placeholder) ko (remove) karein
		state.ui.root.appendChild(content);

		// (UI Elements) ko (cache) karein
		const ui = {
			form: state.ui.root.querySelector('#rsam-report-form'),
			reportType: state.ui.root.querySelector('#rsam-report-type'),
			customerField: state.ui.root.querySelector(
				'#rsam-report-customer-field'
			),
			customerSelect: state.ui.root.querySelector(
				'#rsam-report-customer-id'
			),
			generateBtn: state.ui.root.querySelector(
				'#rsam-generate-report'
			),
			resultsWrapper: state.ui.root.querySelector(
				'#rsam-report-results-wrapper'
			),
		};
		// (UI) ko (state) mein (store) karein
		state.ui.reports = ui;

		// (Event Listeners)
		// (Form) (Submit)
		ui.form.addEventListener('submit', (e) => {
			e.preventDefault();
			handleReportGeneration();
		});

		// (Report Type) (Change)
		ui.reportType.addEventListener('change', (e) => {
			if (e.target.value === 'customer_ledger') {
				ui.customerField.style.display = 'block';
				// (Customer) (dropdown) ko (init) karein (agar nahi hua)
				if (!ui.customerSelect.dataset.initialized) {
					initCustomerSearch(ui.customerSelect, null); // (Quick add) (button) nahi hai
					ui.customerSelect.dataset.initialized = 'true';
				}
			} else {
				ui.customerField.style.display = 'none';
			}
		});

		// (Default) (dates) (set) karein (Mahnay ka start aur end)
		const today = new Date();
		const monthStart = new Date(
			today.getFullYear(),
			today.getMonth(),
			1
		)
			.toISOString()
			.split('T')[0];
		const monthEnd = today.toISOString().split('T')[0];
		ui.form.querySelector('[name="start_date"]').value = monthStart;
		ui.form.querySelector('[name="end_date"]').value = monthEnd;
	}

	/**
	 * (Report) (generation) (AJAX) (request) ko (handle) karta hai.
	 */
	async function handleReportGeneration() {
		const { form, generateBtn, resultsWrapper } = state.ui.reports;

		if (form.checkValidity() === false) {
			form.reportValidity();
			return;
		}

		// (Form) (data) (serialize) karein
		const formData = new FormData(form);
		const data = {};
		formData.forEach((value, key) => (data[key] = value));

		// (Loading) (state)
		resultsWrapper.innerHTML = `<div class="rsam-card rsam-list-loading">
            <p><span class="rsam-loader-spinner"></span> Generating report...</p>
        </div>`;

		try {
			const result = await wpAjax(
				'rsam_generate_report',
				data,
				generateBtn
			);
			renderReportResults(result); // (Results) (render) karein
		} catch (error) {
			showError(error, resultsWrapper);
		}
	}

	/**
	 * (AJAX) (response) ke hisab se (report) ko (render) karta hai.
	 * @param {object} data (Report data)
	 */
	function renderReportResults(data) {
		const { resultsWrapper } = state.ui.reports;
		resultsWrapper.innerHTML = ''; // (Clear) karein

		let html = `<div class="rsam-card rsam-report-results">`;
		html += `<div class="rsam-report-header">
            <h3>Report: ${escapeHtml(
				data.report_type.toUpperCase()
			)}</h3>
            <p>Date Range: ${escapeHtml(
				data.start_date
			)} to ${escapeHtml(data.end_date)}</p>
        </div>`;

		switch (data.report_type) {
			case 'pnl':
				html += renderPnlReport(data.pnl);
				break;
			case 'sales':
				html += renderSalesReport(data);
				break;
			case 'expenses':
				html += renderExpensesReport(data.expenses_summary);
				break;
			case 'stock':
				html += renderStockReport(data.stock_summary);
				break;
			case 'customer_ledger':
				html += renderCustomerLedger(data.customer_ledger);
				break;
			default:
				html += `<p>Error: Unknown report type rendered.</p>`;
		}

		html += `</div>`;
		resultsWrapper.innerHTML = html;
	}

	// --- (Report) (Render) (Helpers) ---

	/**
	 * (Profit & Loss) (Report) (HTML)
	 */
	function renderPnlReport(pnl) {
		let html = `<h4>${rsamData.strings.pnlStatement || 'Profit & Loss Statement'}</h4>
        <div class="rsam-pnl-summary">
            <div class="rsam-summary-row">
                <span>Total Sales (Revenue)</span>
                <span class="rsam-price-positive">${formatPrice(
					pnl.total_sales
				)}</span>
            </div>
            <div class="rsam-summary-row">
                <span>Cost of Goods Sold (COGS)</span>
                <span class="rsam-price-negative">(${formatPrice(
					pnl.total_cogs
				)})</span>
            </div>
            <div class="rsam-summary-row rsam-summary-total">
                <strong>Gross Profit</strong>
                <strong>${formatPrice(pnl.gross_profit)}</strong>
            </div>
            
            <hr>
            <h5>Operating Expenses</h5>
        `;

		// (Expense Breakdown)
		if (pnl.expense_breakdown && pnl.expense_breakdown.length > 0) {
			pnl.expense_breakdown.forEach((exp) => {
				html += `<div class="rsam-summary-row">
                    <span>Expense: ${escapeHtml(exp.category)}</span>
                    <span class="rsam-price-negative">(${formatPrice(
						exp.total
					)})</span>
                </div>`;
			});
		} else {
			html += `<div class="rsam-summary-row"><span>No operating expenses recorded.</span><span>${formatPrice(
				0
			)}</span></div>`;
		}

		html += `<div class="rsam-summary-row rsam-summary-total">
                <strong>Total Operating Expenses</strong>
                <strong class="rsam-price-negative">(${formatPrice(
					pnl.total_expenses
				)})</strong>
            </div>
            <hr>
            <div class="rsam-summary-row rsam-summary-final ${
				pnl.net_profit >= 0
					? 'rsam-price-positive'
					: 'rsam-price-negative'
			}">
                <strong>Net Profit / (Loss)</strong>
                <strong>${formatPrice(pnl.net_profit)}</strong>
            </div>
        </div>`;
		return html;
	}

	/**
	 * (Sales Summary) (Report) (HTML)
	 */
	function renderSalesReport(data) {
		const summary = data.sales_summary;
		let html = `<h4>${rsamData.strings.salesSummary || 'Sales Summary'}</h4>
        <div class="rsam-stats-grid rsam-stats-grid-4col">
            <div class="rsam-stat-item">
                <strong>Total Sales</strong>
                <span>${formatPrice(summary.total_sales)}</span>
            </div>
            <div class="rsam-stat-item">
                <strong>Total Profit</strong>
                <span>${formatPrice(summary.total_profit)}</span>
            </div>
            <div class="rsam-stat-item">
                <strong>Total Transactions</strong>
                <span>${escapeHtml(summary.total_transactions)}</span>
            </div>
            <div class="rsam-stat-item">
                <strong>Total Discounts</strong>
                <span>${formatPrice(summary.total_discount)}</span>
            </div>
        </div>
        
        <hr>
        <h5>Top Selling Products (by Revenue)</h5>
        `;

		// (Top Products) (Table)
		if (data.top_products && data.top_products.length > 0) {
			html += `<table class="rsam-list-table"><thead>
                <tr>
                    <th>Product Name</th>
                    <th>Total Quantity Sold</th>
                    <th>Total Revenue</th>
                </tr>
            </thead><tbody>`;
			data.top_products.forEach((prod) => {
				html += `<tr>
                    <td>${escapeHtml(prod.name)}</td>
                    <td>${escapeHtml(prod.total_quantity)} ${escapeHtml(
					prod.unit_type
				)}</td>
                    <td>${formatPrice(prod.total_revenue)}</td>
                </tr>`;
			});
			html += `</tbody></table>`;
		} else {
			html += `<p>No sales data found for this period.</p>`;
		}
		return html;
	}

	/**
	 * (Expenses Summary) (Report) (HTML)
	 */
	function renderExpensesReport(summary) {
		let html = `<h4>${rsamData.strings.expenseBreakdown || 'Expense Breakdown'}</h4>
        <div class_rsam-summary-row rsam-summary-total">
            <strong>Total Expenses: ${formatPrice(
				summary.total_expenses
			)}</strong>
        </div>
        <hr>
        `;

		// (Expenses) (Table)
		if (
			summary.expense_breakdown &&
			summary.expense_breakdown.length > 0
		) {
			html += `<table class="rsam-list-table"><thead>
                <tr>
                    <th>Expense Category</th>
                    <th>Total Amount</th>
                </tr>
            </thead><tbody>`;
			summary.expense_breakdown.forEach((exp) => {
				html += `<tr>
                    <td>${escapeHtml(exp.category)}</td>
                    <td>${formatPrice(exp.total)}</td>
                </tr>`;
			});
			html += `</tbody></table>`;
		} else {
			html += `<p>No expenses found for this period.</p>`;
		}
		return html;
	}

	/**
	 * (Stock Valuation) (Report) (HTML)
	 */
	function renderStockReport(summary) {
		// (Yeh (report) (date-independent) hai)
		let html = `<h4>${rsamData.strings.inventoryValuation || 'Inventory Valuation Report'} (Real-Time)</h4>
        <div class="rsam-summary-row rsam-summary-total">
            <strong>Total Stock Value (at Cost): ${formatPrice(
				summary.total_stock_value
			)}</strong>
        </div>
        <hr>
        <h5>Stock Value by Category</h5>
        `;

		// (Category Value) (Table)
		if (summary.value_by_category && summary.value_by_category.length > 0) {
			html += `<table class="rsam-list-table"><thead>
                <tr>
                    <th>Category</th>
                    <th>Total Cost Value</th>
                </tr>
            </thead><tbody>`;
			summary.value_by_category.forEach((cat) => {
				html += `<tr>
                    <td>${escapeHtml(
						cat.category || 'Uncategorized'
					)}</td>
                    <td>${formatPrice(cat.total_cost_value)}</td>
                </tr>`;
			});
			html += `</tbody></table>`;
		} else {
			html += `<p>No stock found.</p>`;
		}

		html += `<hr><h5>Low Stock Items</h5>`;
		// (Low Stock) (Table)
		if (
			summary.low_stock_products &&
			summary.low_stock_products.length > 0
		) {
			html += `<table class="rsam-list-table"><thead>
                <tr>
                    <th>Product</th>
                    <th>Current Stock</th>
                    <th>Threshold</th>
                </tr>
            </thead><tbody>`;
			summary.low_stock_products.forEach((prod) => {
				html += `<tr>
                    <td>${escapeHtml(prod.name)}</td>
                    <td>${escapeHtml(prod.stock_quantity)}</td>
                    <td>${escapeHtml(prod.low_stock_threshold)}</td>
                </tr>`;
			});
			html += `</tbody></table>`;
		} else {
			html += `<p>No items are currently low on stock.</p>`;
		}
		return html;
	}

	/**
	 * (Customer Ledger) (Report) (HTML)
	 */
	function renderCustomerLedger(ledger) {
		const customer = ledger.customer;
		let html = `<h4>${rsamData.strings.customerLedger || 'Customer Ledger (Khata)'}</h4>
        <h5>Customer: ${escapeHtml(customer.name)} (${escapeHtml(
			customer.phone
		)})</h5>
        <div class_rsam-summary-row rsam-summary-total">
            <strong>Current Dues (Balance): ${formatPrice(
				customer.credit_balance
			)}</strong>
        </div>
        <hr>
        <h5>Transaction History (Selected Period)</h5>
        `;

		// (Ledger Entries) (Table)
		if (ledger.entries && ledger.entries.length > 0) {
			html += `<table class="rsam-list-table"><thead>
                <tr>
                    <th>Date</th>
                    <th>Transaction Type</th>
                    <th>Details</th>
                    <th>Debit (Dues Added)</th>
                    <th>Credit (Payment Received)</th>
                </tr>
            </thead><tbody>`;

			ledger.entries.forEach((entry) => {
				html += `<tr>
                    <td>${escapeHtml(
						new Date(entry.date).toLocaleDateString()
					)}</td>
                    <td>${escapeHtml(entry.type)}</td>
                    <td>${
						entry.type === 'Sale'
							? `Sale ID #${entry.id}`
							: escapeHtml(entry.notes)
					}</td>
                    <td class="rsam-price-negative">${
						parseFloat(entry.debit) > 0
							? formatPrice(entry.debit)
							: '-'
					}</td>
                    <td class="rsam-price-positive">${
						parseFloat(entry.credit) > 0
							? formatPrice(entry.credit)
							: '-'
					}</td>
                </tr>`;
			});
			html += `</tbody></table>`;
		} else {
			html += `<p>No transactions found for this customer in this period.</p>`;
		}
		return html;
	}

	/** Part 10 — Yahan khatam hua */
		
		
		
	/** Part 1 — Yahan khatam hua */
})(); // (IIFE) (close)
