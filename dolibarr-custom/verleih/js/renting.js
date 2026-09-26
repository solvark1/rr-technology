/* Progressive presentation helpers. Business validation remains server-side. */
document.addEventListener('DOMContentLoaded', function () {
    const app = document.querySelector('.rr-app');
    if (!app) return;
    const seriesList = app.querySelector('#rr-series');
    if (seriesList) {
        const input = app.querySelector('input[name="serial"]');
        const product = input.form.querySelector('select[name="product"]');
        const help = app.querySelector('#rr-series-help');
        const options = Array.from(seriesList.options).map(o => ({product:o.dataset.product, value:o.value}));
        const updateSeries = function () {
            input.value = '';
            seriesList.replaceChildren();
            const matching = options.filter(o => o.product === product.value);
            matching.forEach(function (item) {
                const option = document.createElement('option'); option.value = item.value; seriesList.appendChild(option);
            });
            input.disabled = !product.value || !matching.length;
            input.form.querySelector('button[type="submit"]').disabled = input.disabled;
            if (!product.value) help.textContent = 'Primero elegí un producto para ver sus números de serie.';
            else if (!matching.length) help.textContent = 'Este producto no tiene series con una unidad en el almacén de venta para incorporar. Revisá sus existencias o recargá si acabás de recibir stock.';
            else {
                help.textContent = matching.length + ' serie(s) en el almacén de venta. Abrí las sugerencias o escribí parte del número para buscar. Al guardar se comprueban también los pedidos pendientes.';
                if (matching.length === 1) { input.value = matching[0].value; help.textContent += ' Completamos la única serie encontrada.'; }
            }
        };
        product.addEventListener('change', updateSeries);
        // Dolibarr may enhance selects with Select2, which emits jQuery change events.
        if (window.jQuery) window.jQuery(product).on('change.rrSeries', updateSeries);
        updateSeries();
    }
    const states = {'Disponible':'available','Entregado':'out','En revisión':'review','En reparación':'repair','Destinado a venta':'sale','Reservado':'reserved','En curso':'active','Devolución parcial':'partial','Finalizado':'closed','Cancelado':'cancelled','Bueno':'good','Con desgaste':'worn','Dañado':'damaged','Vencido — revisar':'late'};
    app.querySelectorAll('td').forEach(function (cell) {
        const value = cell.textContent.trim();
        if (cell.children.length || !states[value]) return;
        const badge = document.createElement('span'); badge.className = 'rr-badge ' + states[value];
        badge.textContent = value; cell.replaceChildren(badge);
    });
    app.querySelectorAll('select[name="condition"]').forEach(s => s.setAttribute('aria-label', 'Condición del equipo'));
    app.querySelectorAll('table').forEach(function (table) {
        const wrap = document.createElement('div');
        wrap.className = 'rr-table-wrap';
        table.parentNode.insertBefore(wrap, table); wrap.appendChild(table);
        const rows = Array.from(table.rows).slice(1);
        if (!rows.length) {
            const cell = table.insertRow().insertCell();
            cell.colSpan = table.rows[0].cells.length;
            cell.className = 'rr-empty';
            cell.textContent = 'Todavía no hay registros en esta sección.';
            return;
        }
        if (rows.length > 5) {
            const tools = document.createElement('div'); tools.className = 'rr-tools';
            const input = document.createElement('input'); input.type = 'search';
            input.placeholder = 'Buscar en esta lista…'; input.setAttribute('aria-label', 'Buscar en la lista siguiente');
            const count = document.createElement('span'); count.setAttribute('aria-live', 'polite');
            const filter = function () {
                let visible = 0;
                rows.forEach(function (row) { row.hidden = row.dataset.eligible === 'no' || !row.textContent.toLocaleLowerCase().includes(input.value.toLocaleLowerCase()); if (!row.hidden) visible++; });
                count.textContent = visible + ' de ' + rows.length + ' registros mostrados';
            };
            input.addEventListener('input', filter); tools.append(input, count); wrap.before(tools); filter();
        }
    });
    const contractLine = app.querySelector('select[name="contractline"]');
    if (contractLine) {
        const form = contractLine.form;
        const customer = form.querySelector('[name="soc"]');
        const contract = form.querySelector('[name="contract"]');
        const product = form.querySelector('[name="equipmentproduct"]');
        const contracts = Array.from(contract.options).slice(1).map(o => o.cloneNode(true));
        const lines = Array.from(contractLine.options).slice(1).map(o => o.cloneNode(true));
        const summary = form.querySelector('#rr-contract-summary');
        const choices = Array.from(form.querySelectorAll('[name="assets[]"]'));
        const submit = form.querySelector('button[type="submit"]');
        const refill = function (select, options) {
            while (select.options.length > 1) select.remove(1);
            options.forEach(o => select.appendChild(o.cloneNode(true))); select.value = '';
        };
        const check = function () {
            const option = contractLine.selectedOptions[0];
            const qty = Number(option?.dataset.qty || 0);
            const start = option?.dataset.start || ''; const end = option?.dataset.end || '';
            const valid = Number.isInteger(qty) && qty > 0 && start && end && end >= start;
            let available = 0;
            choices.forEach(function (input) {
                const row = input.closest('tr');
                const busy = JSON.parse(row.dataset.busy || '[]');
                const free = valid && product.value === row.dataset.product && !busy.some(b => (b.date_start <= end && b.date_end >= start) || (row.dataset.state === 'out' && b.date_end < new Date().toLocaleDateString('sv-SE')));
                input.disabled = !free;
                if (!free) input.checked = false;
                row.dataset.eligible = free ? 'yes' : 'no'; row.hidden = !free;
                row.classList.toggle('rr-selected', input.checked);
                if (free) available++;
            });
            const chosen = choices.filter(c => c.checked).length;
            submit.disabled = !valid || !product.value || chosen !== qty || available < qty;
            if (!contractLine.value) summary.textContent = 'Seleccioná una línea del contrato.';
            else if (!valid) summary.textContent = 'Completá en el contrato una cantidad entera positiva y las fechas previstas de inicio y fin.';
            else summary.textContent = 'Periodo del contrato: ' + start + ' → ' + end + '. Requiere ' + qty + ' unidades. Seleccionadas: ' + chosen + ' de ' + qty + (product.value ? '. Disponibles para este periodo: ' + available + (available < qty ? '. No hay suficientes unidades para completar la reserva.' : '.') : '. Elegí el producto físico.');
        };
        const changed = function (element, handler) {
            if (window.jQuery) window.jQuery(element).on('change.rrContract', handler);
            else element.addEventListener('change', handler);
        };
        changed(customer, function () { refill(contract, contracts.filter(o => o.dataset.soc === customer.value)); refill(contractLine, []); check(); });
        changed(contract, function () { refill(contractLine, lines.filter(o => o.dataset.contract === contract.value)); check(); });
        changed(contractLine, function () { choices.forEach(c => c.checked = false); check(); });
        changed(product, check);
        choices.forEach(c => c.addEventListener('change', check));
        refill(contract, []); refill(contractLine, []); check();
    }
    const choices = Array.from(app.querySelectorAll('input[name="assets[]"]'));
    if (choices.length) {
        const counter = document.createElement('p'); counter.className = 'rr-hint'; counter.setAttribute('aria-live', 'polite');
        choices[0].closest('.rr-table-wrap').before(counter);
        const update = function () {
            counter.textContent = choices.filter(c => c.checked).length + ' unidad(es) seleccionadas. Completá la cantidad indicada por el contrato.';
            choices.forEach(c => c.closest('tr').classList.toggle('rr-selected', c.checked));
        };
        choices.forEach(function (c) {
            c.setAttribute('aria-label', 'Seleccionar equipo ' + c.closest('tr').cells[2].textContent);
            c.addEventListener('change', update);
        }); update();
    }
    app.querySelectorAll('form').forEach(function (form) {
        const action = form.querySelector('input[name="action"]');
        if (action && action.value === 'cancel') {
            form.querySelector('button').classList.add('rr-danger');
            form.addEventListener('submit', function (event) {
                if (!window.confirm('¿Cancelar esta reserva y liberar los equipos?')) event.preventDefault();
            });
        }
    });
});

document.addEventListener('DOMContentLoaded',function(){
 document.querySelectorAll('.rr-equipment-search').forEach(function(input){
  const select=input.closest('form').querySelector('select[name="line"]');
  const options=Array.from(select.options).map(o=>({value:o.value,text:o.textContent}));
  const selected=new URLSearchParams(location.search).get('equipment');
  if(selected && options.some(o=>o.value===selected)) select.value=selected;
  input.addEventListener('input',function(){
   const current=select.value; const query=input.value.toLocaleLowerCase();
   select.replaceChildren();
   options.filter(o=>!o.value || o.text.toLocaleLowerCase().includes(query)).forEach(o=>select.add(new Option(o.text,o.value)));
   if(Array.from(select.options).some(o=>o.value===current)) select.value=current;
  });
 });
});

document.addEventListener('DOMContentLoaded',function(){
 document.querySelectorAll('[data-rr-autoload]').forEach(function(select){
  select.addEventListener('change',function(){
   const url=new URL(window.location.href);
   url.search='';
   url.searchParams.set('view','incidents');
   if(select.name==='customer') {
    if(select.value) url.searchParams.set('customer',select.value);
   } else {
    const customer=select.form.querySelector('input[name="customer"]');
    if(customer && customer.value) url.searchParams.set('customer',customer.value);
    if(select.value) url.searchParams.set('id',select.value);
   }
   window.location.assign(url.toString());
  });
 });
});
