/* jshint unused:vars, undef:true, jquery:true, browser:true */

(function($, undefined) {
'use strict';

var SORTORDERCLASS = {
    ASC: 'comtra-sorted-asc',
    DESC: 'comtra-sorted-desc',
};

var Persister = (function() {
    var available, ls, js;
    ls = window.localStorage;
    js = window.JSON;
    available = (ls && ls.getItem && ls.setItem && ls.removeItem && js && js.stringify && js.parse) ? true : false;
    return {
        get: function(key) {
            if (!available) {
                return null;
            }
            var value = ls.getItem(key);
            if (value === null || value === undefined) {
                return null;
            }
            return js.parse(value);
        },
        set: function(key, value) {
            if (!available) {
                return false;
            }
            ls.setItem(key, js.stringify(value));
            return true;
        },
        remove: function(key) {
            if (available) {
                ls.removeItem(key);
            }
        }
    };
})();

function Table(tableElement)
{
    this.$table = $(tableElement);
    if (this.$table.data('comtra-sortable') instanceof Table) {
        return;
    }
    this.persisterKey = this.$table.data('comtra-sortable-persister-key');
    if (this.persisterKey === undefined) {
        this.persisterKey = null;
    }
    this.$table.data('comtra-sortable', this);
    this.rebuild();
    this.sortRows();
    if (this.persisterKey !== null) {
        var sortIndex = Persister.get(this.persisterKey + '-sortIndex');
        if (sortIndex !== null) {
            this.setSortIndex(sortIndex);
        }
    }
}
Table.prototype = {
    rebuild: function() {
        var me = this,
            $theadCells = me.$table.find('>thead>tr>th'),
            $tbody = me.$table.find('>tbody');
        $theadCells.css('cursor', '');
        me.headCells = [];
        me.rowSections = {};
        $tbody.find('>tr').each(function() {
            var $row = $(this),
                rowSortableCells = null,
                populateHeadCells = me.headCells.length === 0;
            $row.find('>th,>td').each(function(cellIndex) {
                var $cell = $(this),
                    sortBy = $cell.data('sortby');
                if (sortBy !== undefined && sortBy !== null) {
                    if (rowSortableCells === null) {
                        rowSortableCells = {};
                    }
                    rowSortableCells[cellIndex] = $cell;
                    if (populateHeadCells) {
                        me.headCells.push({
                            cellIndex: cellIndex,
                            $th: $($theadCells[cellIndex])
                        });
                    }
                }
            });
            if (rowSortableCells !== null) {
                var sectionID = $row.data('sortsection');
                sectionID = (sectionID === undefined || sectionID === null) ? '' : sectionID.toString();
                if (!me.rowSections.hasOwnProperty(sectionID)) {
                    me.rowSections[sectionID] = [];
                }
                me.rowSections[sectionID].push({
                    $row: $row,
                    cells: rowSortableCells
                });
            }
        });
        $.each(me.headCells, function() {
            var cellIndex = this.cellIndex;
            this.$th
                .css('cursor', 'pointer')
                .off('click.comtraSortable')
                .on('click.comtraSortable', function() {
                    me.toggleHeadSort(cellIndex);
                })
            ;
        });
    },
    getSortIndex: function() {
        var result = null;
        $.each(this.headCells, function() {
            if (this.$th.hasClass(SORTORDERCLASS.ASC)) {
                result = {
                    cellIndex: this.cellIndex,
                    desc: false
                };
                return false;
            }
            if (this.$th.hasClass(SORTORDERCLASS.DESC)) {
                result = {
                    cellIndex: this.cellIndex,
                    desc: true
                };
                return false;
            }
        });
        return result;
    },
    setSortIndex: function(sortIndex) {
        $.each(this.headCells, function() {
            this.$th.removeClass(SORTORDERCLASS.ASC + ' ' + SORTORDERCLASS.DESC);
            if (sortIndex !== null && sortIndex.cellIndex === this.cellIndex) {
                this.$th.addClass(SORTORDERCLASS[sortIndex.desc ? 'DESC' : 'ASC']);
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
        var oldSortIndex = this.getSortIndex(), newSortIndex = {cellIndex: cellIndex};
        if (oldSortIndex === null || oldSortIndex.cellIndex !== cellIndex) {
            newSortIndex.desc = false;
            $.each(this.headCells, function() {
                if (this.cellIndex === cellIndex) {
                    if (this.$th.data('sortby-default') === 'desc') {
                        newSortIndex.desc = true;
                    }
                    return false;
                }
            });
        } else {
            newSortIndex.desc = !oldSortIndex.desc;
        }
        this.setSortIndex(newSortIndex);
    },
    sortRows: function() {
        var sortIndex = this.getSortIndex();
        if (sortIndex === null) {
            return;
        }
        $.each(this.rowSections, function() {
            this.sort(function(a, b) {
                var aSortKey = a.cells[sortIndex.cellIndex].data('sortby'),
                    bSortKey = b.cells[sortIndex.cellIndex].data('sortby');
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
                } else if (aSortKey > bSortKey) {
                    return sortIndex.desc ? -1 : 1;
                }
                return 0;
            });
            for (var i = 1; i < this.length; i++) {
                this[i - 1].$row.after(this[i].$row.after());
            }
        });
    }
};

$.fn.comtraSortable = function() {
    return this.each(function() {
        new Table(this);
    });
};

$(document).ready(function() {
    $('table.comtra-sortable').comtraSortable();
});

})(jQuery);