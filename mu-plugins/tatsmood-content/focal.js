/**
 * Centro de interés: al pulsar sobre la foto (campo «Centro de interés» de Medios) se
 * mueve el punto y se guarda la posición en % en el campo oculto. El «change» hace que
 * WordPress lo guarde solo, como el resto de campos del adjunto.
 */
jQuery(function ($) {
  $(document).on('click', '[data-tm-focal]', function (e) {
    const rect = this.getBoundingClientRect();
    const x = Math.round(((e.clientX - rect.left) / rect.width) * 1000) / 10;
    const y = Math.round(((e.clientY - rect.top) / rect.height) * 1000) / 10;
    const cx = Math.min(100, Math.max(0, x));
    const cy = Math.min(100, Math.max(0, y));
    $(this).find('.tm-focal-dot').css({ left: cx + '%', top: cy + '%' });
    $(this).nextAll('input[type=hidden]').first().val(cx + ',' + cy).trigger('change');
  });
});
