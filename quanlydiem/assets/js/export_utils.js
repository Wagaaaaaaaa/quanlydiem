// Các hàm xuất Excel/PDF giữ nguyên định dạng (màu sắc, viền, chữ đậm...) như trên web.
// Dùng chung cho tất cả các trang quản lý.

// Sao chép style đã render (computed style) thành inline style trên chính bảng nguồn
// để khi clone/copy sang Excel/PDF vẫn giữ được giao diện.
function _copyComputedToTable(table) {
    var nodes = table.querySelectorAll('th, td, tr, table');
    nodes.forEach(function (el) {
        var cs = window.getComputedStyle(el);
        if (!cs) return;
        el.style.backgroundColor = cs.backgroundColor;
        el.style.color = cs.color;
        el.style.fontWeight = cs.fontWeight;
        el.style.fontSize = cs.fontSize;
        el.style.fontStyle = cs.fontStyle;
        el.style.textAlign = cs.textAlign;
        el.style.verticalAlign = cs.verticalAlign;
        el.style.borderTop = cs.borderTopWidth + ' ' + cs.borderTopStyle + ' ' + cs.borderTopColor;
        el.style.borderRight = cs.borderRightWidth + ' ' + cs.borderRightStyle + ' ' + cs.borderRightColor;
        el.style.borderBottom = cs.borderBottomWidth + ' ' + cs.borderBottomStyle + ' ' + cs.borderBottomColor;
        el.style.borderLeft = cs.borderLeftWidth + ' ' + cs.borderLeftStyle + ' ' + cs.borderLeftColor;
        el.style.padding = cs.padding;
        el.style.borderCollapse = 'collapse';
    });
}

// Xuất Excel giữ định dạng: xuất bảng HTML (.xls) mở trực tiếp, vẫn giữ màu nền/viền/chữ.
function improvedExportToExcel(tableID, filename) {
    var table = document.getElementById(tableID);
    if (!table) return;

    // Copy style trước khi clone để không thay đổi bảng hiển thị (vì clone không ảnh hưởng)
    var cloned = table.cloneNode(true);
    // Ẩn bớt cột Thao tác
    cloned.querySelectorAll('[data-exclude="true"]').forEach(function (el) { el.remove(); });

    // Copy computed style từ các cell tương ứng trong bảng thật (cùng chỉ số hàng/cột sau khi loại cột exclude)
    var srcRows = table.querySelectorAll('tr');
    var cloneRows = cloned.querySelectorAll('tr');
    for (var r = 0; r < cloneRows.length; r++) {
        var srcCells = srcRows[r] ? srcRows[r].querySelectorAll('th, td') : [];
        var cloneCells = cloneRows[r].querySelectorAll('th, td');
        // Bỏ qua các cột đã bị loại ở hàng này
        var keptSrc = [];
        srcCells.forEach(function (c) { if (!c.closest('[data-exclude="true"]')) keptSrc.push(c); });
        for (var c = 0; c < cloneCells.length && c < keptSrc.length; c++) {
            var cs = window.getComputedStyle(keptSrc[c]);
            cloneCells[c].style.backgroundColor = cs.backgroundColor;
            cloneCells[c].style.color = cs.color;
            cloneCells[c].style.fontWeight = cs.fontWeight;
            cloneCells[c].style.fontSize = cs.fontSize;
            cloneCells[c].style.textAlign = cs.textAlign;
            cloneCells[c].style.border = '1px solid #cccccc';
            cloneCells[c].style.padding = '6px 10px';
        }
    }

    var html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    html += '<head><meta charset="UTF-8"></head><body>';
    html += '<table border="1" cellspacing="0" cellpadding="4" style="border-collapse:collapse;font-family:Arial,sans-serif;">';
    html += cloned.innerHTML;
    html += '</table></body></html>';

    var blob = new Blob(['\ufeff' + html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
    var link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = (filename || 'Export') + '.xls';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);
}

// Xuất PDF giữ nguyên màu sắc/viền/badge như trên web.
function improvedExportToPDF(tableID, filename) {
    var table = document.getElementById(tableID);
    if (!table) return;

    var wrapper = document.createElement('div');
    wrapper.style.padding = '20px';
    wrapper.style.background = '#ffffff';
    wrapper.style.fontFamily = "'Plus Jakarta Sans', Arial, sans-serif";

    var h2 = document.createElement('h2');
    h2.innerText = document.title || 'Danh sách';
    h2.style.textAlign = 'center';
    h2.style.marginBottom = '15px';
    h2.style.color = '#1e293b';
    wrapper.appendChild(h2);

    var cloned = table.cloneNode(true);
    cloned.querySelectorAll('[data-exclude="true"]').forEach(function (el) { el.remove(); });

    var srcRows = table.querySelectorAll('tr');
    var cloneRows = cloned.querySelectorAll('tr');
    for (var r = 0; r < cloneRows.length; r++) {
        var srcCells = srcRows[r] ? srcRows[r].querySelectorAll('th, td') : [];
        var cloneCells = cloneRows[r].querySelectorAll('th, td');
        var keptSrc = [];
        srcCells.forEach(function (c) { if (!c.closest('[data-exclude="true"]')) keptSrc.push(c); });
        for (var c = 0; c < cloneCells.length && c < keptSrc.length; c++) {
            var cs = window.getComputedStyle(keptSrc[c]);
            cloneCells[c].style.backgroundColor = cs.backgroundColor;
            cloneCells[c].style.color = cs.color;
            cloneCells[c].style.fontWeight = cs.fontWeight;
            cloneCells[c].style.fontSize = '11px';
            cloneCells[c].style.textAlign = cs.textAlign;
            cloneCells[c].style.border = '1px solid #e2e8f0';
            cloneCells[c].style.padding = '8px';
        }
    }
    cloned.style.width = '100%';
    cloned.style.borderCollapse = 'collapse';
    wrapper.appendChild(cloned);

    document.body.appendChild(wrapper);
    html2pdf().set({
        margin: 10,
        filename: (filename || 'Export') + '.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' }
    }).from(wrapper).save().then(function () { wrapper.remove(); });
}
