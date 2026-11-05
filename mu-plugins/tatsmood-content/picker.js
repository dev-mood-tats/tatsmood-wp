/**
 * Listas ordenables de la página «Web tatsmood» (colecciones de la home y Featured):
 * tarjetas con foto que se ordenan arrastrando, se quitan y se añaden desde un desplegable.
 * Cada lista guarda los ids, en orden, en su campo oculto.
 * Config por lista en window.tatsmoodPickers[id] = { items, selected, max, horizontal, addLabel }.
 */
jQuery(function ($) {
  Object.entries(window.tatsmoodPickers || {}).forEach(([id, cfg]) => {
    const byId = Object.fromEntries(cfg.items.map((p) => [p.id, p]));
    let selected = cfg.selected.filter((x) => byId[x]);
    const $root = $('#' + id);
    const $input = $('#' + id + '-input');

    function render() {
      $input.val(selected.join(','));
      $root.empty();
      const $list = $('<div class="tm-cards"></div>').toggleClass('is-vertical', !cfg.horizontal).appendTo($root);

      selected.forEach((itemId, i) => {
        const p = byId[itemId];
        $('<div class="tm-card"></div>')
          .attr('data-id', itemId)
          .append(
            $('<span class="tm-card-pos"></span>').text(i + 1),
            $('<div class="tm-card-img"></div>').append(p.thumb ? $('<img alt="">').attr('src', p.thumb) : ''),
            $('<div class="tm-card-body"></div>').append(
              $('<span class="tm-card-name"></span>').text(p.name),
              p.note ? $('<span class="tm-card-note"></span>').text(p.note) : '',
              $('<button type="button" class="button-link tm-card-remove">Quitar</button>').on('click', () => {
                selected = selected.filter((x) => x !== itemId);
                render();
              }),
            ),
          )
          .appendTo($list);
      });
      if (cfg.max) for (let i = selected.length; i < cfg.max; i++) $('<div class="tm-card is-empty"><span>Hueco libre</span></div>').appendTo($list);
      if (!selected.length && !cfg.max) $('<p class="tm-empty">No hay ninguna.</p>').appendTo($list);

      $list.sortable({
        items: '> .tm-card:not(.is-empty)',
        tolerance: 'pointer',
        axis: cfg.horizontal ? false : 'y',
        placeholder: 'tm-card tm-card-placeholder',
        forcePlaceholderSize: true,
        update() {
          selected = $list.children('.tm-card:not(.is-empty)').map((_, el) => Number(el.dataset.id)).get();
          render();
        },
      });

      if (!cfg.max || selected.length < cfg.max) {
        const $select = $('<select></select>').append($('<option value=""></option>').text(cfg.addLabel));
        cfg.items.filter((p) => !selected.includes(p.id)).forEach((p) => $('<option></option>').val(p.id).text(p.name).appendTo($select));
        $select.on('change', function () {
          if (this.value) {
            selected.push(Number(this.value));
            render();
          }
        });
        $('<p class="tm-cards-add"></p>').append($select).appendTo($root);
      } else {
        $('<p class="description"></p>').text('Ya hay ' + cfg.max + ': quita uno para añadir otro.').appendTo($root);
      }
    }
    render();
  });
});
