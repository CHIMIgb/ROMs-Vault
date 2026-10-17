// views/admin/categorias/edit.php - vista previa del color
(function () {
    'use strict';

    const data = JSON.parse(
        (document.getElementById('rv-data-admin-categorias-edit') || {}).textContent || '{}'
    );
    const btn = document.getElementById('btn-eliminar-categoria');
    if (!btn) return;
    btn.addEventListener('click', function () {
        const id     = this.dataset.id;
        const nombre = this.dataset.nombre;
        RVAlerts.confirm({
            tipo:      'danger',
            titulo:    data.t['admin.categoria.confirm_eliminar_titulo'],
            mensaje:   data.t['admin.categoria.confirm_eliminar'].replace('{n}', nombre),
            btnOk:     data.t['admin.categoria.si_eliminar'],
            btnCancel: data.t['admin.categoria.cancelar'],
            onOk: () => {
                document.getElementById('form-eliminar-categoria').submit();
            }
        });
    });
})();
