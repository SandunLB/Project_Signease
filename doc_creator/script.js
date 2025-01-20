document.addEventListener('DOMContentLoaded', function() {
    const { jsPDF } = window.jspdf;

    // Register the better-table module with Quill
    Quill.register({
        'modules/better-table': quillBetterTable
    }, true);

    const toolbarOptions = [
        ['bold', 'italic', 'underline', 'strike'],
        ['blockquote', 'code-block'],
        [{ 'header': 1 }, { 'header': 2 }],
        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
        [{ 'script': 'sub'}, { 'script': 'super' }],
        [{ 'indent': '-1'}, { 'indent': '+1' }],
        [{ 'direction': 'rtl' }],
        [{ 'size': ['small', false, 'large', 'huge'] }],
        [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
        [{ 'color': [] }, { 'background': [] }],
        [{ 'font': [] }],
        [{ 'align': [] }],
        ['clean']
    ];

    const quill = new Quill('#editor', {
        modules: {
            toolbar: toolbarOptions,
            table: false,
            'better-table': {
                operationMenu: {
                    items: {
                        unmergeCells: {
                            text: 'Unmerge Cells'
                        }
                    }
                }
            },
            keyboard: {
                bindings: quillBetterTable.keyboardBindings
            }
        },
        theme: 'snow'
    });

    // Set default font size and color
    quill.format('size', 'small');
    quill.format('color', '#000000');

    // Table control handlers
    document.getElementById('insert-table').addEventListener('click', function() {
        const tableModule = quill.getModule('better-table');
        tableModule.insertTable(3, 3);
    });

    document.getElementById('add-row').addEventListener('click', function() {
        const tableModule = quill.getModule('better-table');
        tableModule.insertRow();
    });

    document.getElementById('add-column').addEventListener('click', function() {
        const tableModule = quill.getModule('better-table');
        tableModule.insertColumn();
    });

    document.getElementById('remove-row').addEventListener('click', function() {
        const tableModule = quill.getModule('better-table');
        tableModule.deleteRow();
    });

    document.getElementById('remove-column').addEventListener('click', function() {
        const tableModule = quill.getModule('better-table');
        tableModule.deleteColumn();
    });

    document.getElementById('addPage').addEventListener('click', function() {
        const currentHeight = document.querySelector('#editor .ql-editor').offsetHeight;
        const newHeight = currentHeight + 297; // 297mm is A4 height
        document.querySelector('#editor .ql-editor').style.minHeight = `${newHeight}mm`;
    });

    document.getElementById('downloadPdf').addEventListener('click', function() {
        const doc = new jsPDF({
            unit: 'mm',
            format: 'a4'
        });

        const content = quill.getContents();
        let y = 20; // Start at 20mm from top

        content.ops.forEach(op => {
            if (typeof op.insert === 'string') {
                // Handle regular text
                handleTextContent(doc, op, y);
                y += getContentHeight(op);
            } else if (typeof op.insert === 'object' && op.insert.table) {
                // Handle table
                y = handleTableContent(doc, op.insert.table, y);
            }

            // Add page if needed
            if (y > 277) {
                doc.addPage();
                y = 20;
            }
        });

        doc.save('Accurate_A4_document.pdf');
    });

    function handleTextContent(doc, op, y) {
        const fontSize = getFontSize(op.attributes && op.attributes.size ? op.attributes.size : 'small');
        doc.setFontSize(fontSize);

        if (op.attributes && op.attributes.bold) doc.setFont(undefined, 'bold');
        else if (op.attributes && op.attributes.italic) doc.setFont(undefined, 'italic');
        else doc.setFont(undefined, 'normal');

        if (op.attributes && op.attributes.color) {
            const color = hexToRgb(op.attributes.color);
            doc.setTextColor(color.r, color.g, color.b);
        } else {
            doc.setTextColor(0, 0, 0);
        }

        const align = op.attributes && op.attributes.align ? op.attributes.align : 'left';
        const lines = doc.splitTextToSize(op.insert, 170);
        lines.forEach(line => {
            const xPosition = getAlignPosition(align, doc.getStringUnitWidth(line) * fontSize);
            doc.text(line, xPosition, y);
            y += fontSize * 0.3527777778;
        });
    }

    function handleTableContent(doc, table, startY) {
        const cellPadding = 2; // mm
        const cellHeight = 10; // mm
        let currentY = startY;

        // Calculate column widths (distribute evenly)
        const tableWidth = 170; // 210mm - 2*20mm margin
        const columnCount = table[0].length;
        const cellWidth = tableWidth / columnCount;

        // Draw table
        table.forEach((row, rowIndex) => {
            let maxCellHeight = cellHeight;

            // Draw cells
            row.forEach((cell, colIndex) => {
                const x = 20 + (colIndex * cellWidth);
                
                // Draw cell border
                doc.rect(x, currentY, cellWidth, cellHeight);

                // Draw cell content
                doc.setFontSize(9); // Use small font for table
                const cellContent = cell.content || '';
                const lines = doc.splitTextToSize(cellContent, cellWidth - (2 * cellPadding));
                
                lines.forEach((line, lineIndex) => {
                    doc.text(line, x + cellPadding, currentY + cellPadding + (lineIndex * 3.5));
                });

                // Update max cell height if needed
                const contentHeight = lines.length * 3.5 + (2 * cellPadding);
                maxCellHeight = Math.max(maxCellHeight, contentHeight);
            });

            currentY += maxCellHeight;
        });

        return currentY;
    }

    function getFontSize(size) {
        const sizes = {
            'small': 9,
            'large': 16,
            'huge': 24
        };
        return sizes[size] || 12;
    }

    function hexToRgb(hex) {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        } : null;
    }

    function getAlignPosition(align, textWidth) {
        switch (align) {
            case 'center':
                return (210 - textWidth) / 2;
            case 'right':
                return 190 - textWidth;
            default:
                return 20;
        }
    }

    function getContentHeight(op) {
        const fontSize = getFontSize(op.attributes && op.attributes.size ? op.attributes.size : 'small');
        return fontSize * 0.3527777778 * (op.insert.split('\n').length);
    }
});