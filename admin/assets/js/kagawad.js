function showList() {
  document.getElementById('view-list').style.display = '';
  document.getElementById('view-form').style.display = 'none';
}

function exportEventsCSV() {
  var rows = [['Event Name','Date','Location','Status']];
  document.querySelectorAll('#view-list table tbody tr').forEach(function(tr) {
    var cells = tr.querySelectorAll('td');
    if (cells.length >= 4) {
      rows.push([
        cells[0].innerText.trim(),
        cells[1].innerText.trim(),
        cells[2].innerText.trim(),
        cells[3].innerText.trim()
      ]);
    }
  });
  var csv = rows.map(function(r) {
    return r.map(function(c) { return '"' + c.replace(/"/g,'""') + '"'; }).join(',');
  }).join('\n');
  var a = document.createElement('a');
  a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
  a.download = 'events.csv';
  a.click();
}