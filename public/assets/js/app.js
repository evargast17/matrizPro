document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('table.data-table').forEach(tbl=>{
    new DataTable(tbl, {
      responsive:true, fixedHeader:true,
      layout:{ topStart: { buttons: ['copy','csv','excel','pdf','print'] } }
    });
  });
});