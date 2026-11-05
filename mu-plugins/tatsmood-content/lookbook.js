/**
 * Editor visual del lookbook (Productos → Collections → Editar).
 * Pinta cada fila tal y como se verá en la web (huecos en su posición y ancho reales),
 * permite reordenar filas arrastrando, cambiar la plantilla, elegir fotos y recortes.
 * Lo guarda como JSON en el campo oculto #tatsmood-lookbook-input.
 */
jQuery(function ($) {
  const data = window.tatsmoodLookbook || { rows: [], layouts: {} };
  const layouts = data.layouts;
  let rows = data.rows || [];
  const $root = $('#tatsmood-lookbook');
  const $input = $('#tatsmood-lookbook-input');
  const CROPS = { '': 'Original', '4:3': '4:3', '1:1': '1:1', '4:5': '4:5' };
  const RATIO = { '4:3': 4 / 3, '1:1': 1, '4:5': 4 / 5 };

  function save() {
    $input.val(
      JSON.stringify(
        rows.map((r) => ({
          layout: r.layout,
          tight: !!r.tight,
          images: r.images.map((i) => ({ id: i.id || 0, crop: i.crop || '', link: i.link || '' })),
        })),
      ),
    );
  }

  // Miniatura de una plantilla: rectángulos en la posición de cada hueco.
  function layoutIcon(key) {
    const $icon = $('<span class="tm-layout-icon"></span>');
    // Columnas en su proporción (altura orientativa: las más anchas, más altas).
    const cols = layouts[key].cols;
    const total = cols.reduce((a, b) => a + b, 0);
    const span = 88 * ((layouts[key].width || 100) / 100);
    let left = 6 + (88 - span) / 2;
    cols.forEach((c) => {
      const w = ((span - (cols.length - 1) * 3) * c) / total;
      $('<span></span>').css({ left: left + '%', width: w + '%', height: (c / total > 0.55 ? 88 : 66) + '%' }).appendTo($icon);
      left += w + 3;
    });
    return $icon;
  }

  function layoutPicker(current, onPick) {
    const $p = $('<div class="tm-layouts"></div>');
    Object.keys(layouts).forEach((key) => {
      if (layouts[key].hidden && key !== current) return;
      $('<button type="button" class="tm-layout"></button>')
        .toggleClass('is-active', key === current)
        .append(layoutIcon(key), $('<span class="tm-layout-label"></span>').text(layouts[key].label))
        .on('click', () => onPick(key))
        .appendTo($p);
    });
    return $p;
  }

  function pickImage(onSelect) {
    const frame = wp.media({ title: 'Elegir foto', library: { type: 'image' }, multiple: false, button: { text: 'Usar esta foto' } });
    frame.on('select', () => {
      const a = frame.state().get('selection').first().toJSON();
      // La original: los tamaños intermedios pueden no existir en el servidor.
      onSelect({ id: a.id, url: a.url, w: a.width, h: a.height });
    });
    frame.open();
  }

  function renderRow(row, index) {
    const cols = layouts[row.layout].cols;
    while (row.images.length < cols.length) row.images.push({ id: 0, crop: '' });
    row.images = row.images.slice(0, cols.length);

    const $row = $('<div class="tm-row"></div>').attr('data-index', index).toggleClass('is-tight', !!row.tight);
    const $bar = $('<div class="tm-row-bar"></div>').appendTo($row);
    $('<span class="tm-handle" title="Arrastrar para mover">⠿</span>').appendTo($bar);
    $('<strong></strong>').text('Fila ' + (index + 1)).appendTo($bar);

    const $layoutBtn = $('<button type="button" class="button button-small"></button>')
      .append(layoutIcon(row.layout).addClass('is-small'), ' ', $('<span></span>').text(layouts[row.layout].label))
      .appendTo($bar);
    $layoutBtn.on('click', () => {
      $row.find('.tm-layouts').length
        ? $row.find('.tm-layouts').remove()
        : $bar.after(
            layoutPicker(row.layout, (key) => {
              row.layout = key;
              render();
            }),
          );
    });

    $('<label class="tm-tight"></label>')
      .append(
        $('<input type="checkbox">')
          .prop('checked', !!row.tight)
          .on('change', function () {
            row.tight = this.checked;
            render();
          }),
        ' Pegada a la fila de arriba',
      )
      .appendTo($bar);

    $('<button type="button" class="button-link tm-delete">Eliminar fila</button>')
      .on('click', () => {
        if (window.confirm('¿Eliminar esta fila?')) {
          rows.splice(index, 1);
          render();
        }
      })
      .appendTo($bar);

    // La fila como en la web: las fotos se reparten el ancho en columnas.
    const $page = $('<div class="tm-page"></div>')
      .css('grid-template-columns', cols.map((c) => c + 'fr').join(' '))
      // Filas de una foto: más estrechas y centradas, como en la web.
      .css('padding-inline', layouts[row.layout].width ? `calc(10px + ${(100 - layouts[row.layout].width) / 2}%)` : '')
      .appendTo($row);
    cols.forEach((_, i) => {
      const img = row.images[i];
      const $slot = $('<div class="tm-slot"></div>');
      const $frame = $('<button type="button" class="tm-frame"></button>').appendTo($slot);
      if (img.id && img.url) {
        const ratio = RATIO[img.crop] || (img.w && img.h ? img.w / img.h : 4 / 5);
        const focal = img.focal || [50, 50];
        $frame
          .css({ 'aspect-ratio': String(ratio), '--focal': focal[0] + '% ' + focal[1] + '%' })
          .append($('<img alt="">').attr('src', img.url))
          .attr('title', 'Cambiar foto');
      } else {
        $frame.addClass('is-empty').css('aspect-ratio', '4 / 5').append('<span>+ Elegir foto</span>');
      }
      $frame.on('click', () =>
        pickImage((picked) => {
          row.images[i] = Object.assign({ crop: img.crop || '', link: img.link || '' }, picked);
          render();
        }),
      );
      const $tools = $('<div class="tm-slot-tools"></div>').appendTo($slot);
      const $crop = $('<select></select>').appendTo($tools);
      Object.keys(CROPS).forEach((k) => $('<option></option>').val(k).text(k ? 'Recorte ' + CROPS[k] : 'Proporción original').appendTo($crop));
      $crop.val(img.crop || '').on('change', function () {
        img.crop = this.value;
        render();
      });
      if (img.id) {
        $('<button type="button" class="button-link">Quitar</button>')
          .on('click', () => {
            row.images[i] = { id: 0, crop: '' };
            render();
          })
          .appendTo($tools);
      }
      // Enlace de la foto (opcional), con sugerencias de productos, colecciones y páginas.
      if (img.id) {
        $('<input type="text" class="tm-link" list="tm-link-options" placeholder="Enlace (opcional): /product/…">')
          .val(img.link || '')
          .attr('title', img.link || 'Sin enlace')
          .on('input change', function () {
            img.link = this.value.trim();
            save();
          })
          .appendTo($slot);
      }
      $page.append($slot);
    });
    return $row;
  }

  function render() {
    $root.empty();
    const $list = $('<div class="tm-rows"></div>').appendTo($root);
    rows.forEach((row, i) => $list.append(renderRow(row, i)));
    if (!rows.length) $list.append('<p class="tm-empty">Todavía no hay fotos en el lookbook.</p>');

    $list.sortable({
      handle: '.tm-handle',
      items: '> .tm-row',
      axis: 'y',
      placeholder: 'tm-row-placeholder',
      forcePlaceholderSize: true,
      update() {
        const order = $list.children('.tm-row').map((_, el) => Number(el.dataset.index)).get();
        rows = order.map((i) => rows[i]);
        render();
      },
    });

    const $add = $('<div class="tm-add"><p><strong>Añadir fila</strong> — elige cómo van las fotos:</p></div>').appendTo($root);
    $add.append(
      layoutPicker(null, (key) => {
        rows.push({ layout: key, tight: false, images: [] });
        render();
      }),
    );
    save();
  }

  // Lista de sugerencias para los campos «Enlace».
  const $options = $('<datalist id="tm-link-options"></datalist>').appendTo(document.body);
  (data.links || []).forEach((l) => $('<option></option>').val(l.value).text(l.label).appendTo($options));

  render();
});
