// views/admin/consolas/edit.php - vista previa del color
(function () {
    'use strict';

    const data = JSON.parse(
        (document.getElementById('rv-data-admin-consolas-edit') || {}).textContent || '{}'
    );
    const btn = document.getElementById('btn-eliminar-consola');
    if (!btn) return;
    btn.addEventListener('click', function () {
        const id     = this.dataset.id;
        const nombre = this.dataset.nombre;
        RVAlerts.confirm({
            tipo:      'danger',
            titulo:    data.t['admin.consola.confirm_eliminar_titulo'],
            mensaje:   data.t['admin.consola.confirm_eliminar'].replace('{n}', nombre),
            btnOk:     data.t['admin.consola.si_eliminar'],
            btnCancel: data.t['admin.consola.cancelar'],
            onOk: () => {
                document.getElementById('form-eliminar-consola').submit();
            }
        });
    });
})();
