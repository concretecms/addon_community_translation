(function($, undefined) {
'use strict';

const SORTORDERCLASS = {
    ASC: 'table-sortable-sorted-asc',
    DESC: 'table-sortable-sorted-desc',
};

const Persister = (function() {
    const storage = window.localStorage;
    const json = window.JSON;
    const available = (storage && storage.getItem && storage.setItem && storage.removeItem && json && json.stringify && json.parse) ? true : false;
    return {
        get: function(key) {
            if (!available) {
                return null;
            }
            let value = storage.getItem(key);
            if (value === null || value === undefined) {
                return null;
            }
            return json.parse(value);
        },
        set: function(key, value) {
            if (!available) {
                return false;
            }
            storage.setItem(key, json.stringify(value));
            return true;
        },
        remove: function(key) {
            if (available) {
                storage.removeItem(key);
            }
        }
    };
})();

function Table(tableElement)
{
    this.$table = $(tableElement);
    if (this.$table.data('table-sortable') instanceof Table) {
        return;
    }
    this.persisterKey = this.$table.data('table-sortable-persister-key');
    if (this.persisterKey === undefined) {
        this.persisterKey = null;
    }
    this.$table.data('table-sortable', this);
    this.rebuild();
    this.sortRows();
    if (this.persisterKey !== null) {
        const sortIndex = Persister.get(this.persisterKey + '-sortIndex');
        if (sortIndex !== null) {
            this.setSortIndex(sortIndex);
        }
    }
}
Table.prototype = {
    rebuild: function() {
        const $theadCells = this.$table.find('>thead>tr>th');
        const $tbody = this.$table.find('>tbody');
        $theadCells.css('cursor', '');
        this.headCells = [];
        this.rowSections = {};
        $tbody.find('>tr').each((_rowIndex, row) => {
            const $row = $(row);
            const populateHeadCells = this.headCells.length === 0;
            let rowSortableCells = null;
            $row.find('>th,>td').each((cellIndex, cell) => {
                const $cell = $(cell);
                const sortBy = $cell.data('sortby');
                if (sortBy !== undefined && sortBy !== null) {
                    if (rowSortableCells === null) {
                        rowSortableCells = {};
                    }
                    rowSortableCells[cellIndex] = $cell;
                    if (populateHeadCells) {
                        this.headCells.push({
                            cellIndex: cellIndex,
                            $th: $($theadCells[cellIndex]),
                        });
                    }
                }
            });
            if (rowSortableCells !== null) {
                const rawSectionID = $row.data('sortsection');
                const sectionID = (rawSectionID === undefined || rawSectionID === null) ? '' : rawSectionID.toString();
                if (!this.rowSections.hasOwnProperty(sectionID)) {
                    this.rowSections[sectionID] = [];
                }
                this.rowSections[sectionID].push({
                    $row: $row,
                    cells: rowSortableCells,
                });
            }
        });
        this.headCells.forEach((headCell) => {
            const cellIndex = headCell.cellIndex;
            headCell.$th
                .css('cursor', 'pointer')
                .off('click.table-sortable')
                .on('click.table-sortable', () => {
                    this.toggleHeadSort(cellIndex);
                })
            ;
        });
    },
    getSortIndex: function() {
        let result = null;
        this.headCells.some((headCell) => {
            if (headCell.$th.hasClass(SORTORDERCLASS.ASC)) {
                result = {
                    cellIndex: headCell.cellIndex,
                    desc: false,
                };
                return true;
            }
            if (headCell.$th.hasClass(SORTORDERCLASS.DESC)) {
                result = {
                    cellIndex: headCell.cellIndex,
                    desc: true
                };
                return true;
            }
            return false;
        });
        return result;
    },
    setSortIndex: function(sortIndex) {
        this.headCells.forEach((headCell) => {
            headCell.$th.removeClass(SORTORDERCLASS.ASC + ' ' + SORTORDERCLASS.DESC);
            if (sortIndex !== null && sortIndex.cellIndex === headCell.cellIndex) {
                headCell.$th.addClass(SORTORDERCLASS[sortIndex.desc ? 'DESC' : 'ASC']);
            }
        });
        if (this.persisterKey !== null) {
            if (sortIndex === null) {
                Persister.remove(this.persisterKey + '-sortIndex');
            } else {
                Persister.set(this.persisterKey + '-sortIndex', sortIndex);
            }
        }
        this.sortRows();
    },
    toggleHeadSort: function(cellIndex) {
        const oldSortIndex = this.getSortIndex();
        const newSortIndex = {cellIndex: cellIndex};
        if (oldSortIndex === null || oldSortIndex.cellIndex !== cellIndex) {
            newSortIndex.desc = false;
            this.headCells.some((headCell) => {
                if (headCell.cellIndex !== cellIndex) {
                    return false;
                }
                if (headCell.$th.data('sortby-default') === 'desc') {
                    newSortIndex.desc = true;
                }
                return true;
            });
        } else {
            newSortIndex.desc = !oldSortIndex.desc;
        }
        this.setSortIndex(newSortIndex);
    },
    sortRows: function() {
        const sortIndex = this.getSortIndex();
        if (sortIndex === null) {
            return;
        }
        for (let sectionID in this.rowSections) {
            const rowSection = this.rowSections[sectionID];
            rowSection.sort((a, b) => {
                let aSortKey = a.cells[sortIndex.cellIndex].data('sortby');
                let bSortKey = b.cells[sortIndex.cellIndex].data('sortby');
                switch(a.cells[sortIndex.cellIndex].data('data-sortby-kind')) {
                    case 'numeric':
                        aSortKey = aSortKey ? parseFloat(aSortKey) : 0;
                        break;
                }
                switch(b.cells[sortIndex.cellIndex].data('data-sortby-kind')) {
                    case 'numeric':
                        bSortKey = bSortKey ? parseFloat(bSortKey) : 0;
                        break;
                }
                if (aSortKey < bSortKey) {
                    return sortIndex.desc ? 1 : -1;
                }
                if (aSortKey > bSortKey) {
                    return sortIndex.desc ? -1 : 1;
                }
                return 0;
            });
            for (let i = 1; i < rowSection.length; i++) {
                rowSection[i - 1].$row.after(rowSection[i].$row.after());
            }
        }
    },
};

$.fn.tableSortable = function() {
    return this.each(function() {
        new Table(this);
    });
};

$(document).ready(function() {
    $('table.table-sortable').tableSortable();
});

})(jQuery);
