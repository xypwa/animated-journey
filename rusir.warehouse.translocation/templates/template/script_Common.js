if (typeof BI == 'undefined')
    var BI = {};
BI.onReady = [];
BI.isReady = false;
BI.filterSelectValues = function(select, values) {
    console.log('1')
    for (var i = select.options.length - 1; i >= 0; i--) {
        var option = select.options[i];
        console.log(option);
        if (option.value && values.indexOf(option.value) == -1) {
            console.log('Removing');
            option.remove();
        }
    }

};
BI.filterNewSelectValues = function(div, values) {
    console.log('1')
    if (!div.length)
        return;
    let data = BX.data(div[0], 'items');
    if (!BX.type.isArray(data))
        data = JSON.parse(data);

    let newData = [];
    for (var i = data.length - 1; i >= 0; i--) {
        var option = data[i];
        console.log(option);
        if (!option.VALUE || values.indexOf(option.VALUE) >= 0)
            newData.push(option);
    }
    BX.data(div[0], 'items', newData);
};
BI.ready = function(cb) {
    console.log('1')
    if (BI.isReady)
        BX.ready(cb);
    else
        BI.onReady.push(cb);
};
BI.setReady = function() {
    console.log('1')
    if (!BI.isReady) {
        BI.isReady = true;
        for (let cb of BI.onReady)
            BX.ready(cb);
        BI.onReady = [];
    }
};

BX(function(){
    var oldFunc = BX.Bizproc.postTaskForm;
    BX.Bizproc.postTaskForm = function (form, e)
    {
        var actionName, actionValue, btn = document.activeElement;
        if ((!btn || !btn.type) && !e.explicitOriginalTarget && e.submitter)
        {
            e.explicitOriginalTarget = e.submitter;
        }
        oldFunc(form, e);
    };
});

BI.ready(function(){
    console.log('1')
    var form = document.forms[BI.taskFormId],
        submiter = BX(BI.taskFormId + '_submiter');
    /*var children = BX.findChildren(form, {property: {type: 'submit'}}, true);
    if (!children || !children.length)
	    children = BX.findChildren(form, {property: {type: 'button'}}, true);

    for (var i=0; i<children.length; i++)
    {
        var cb = function()
        {
            console.log('1')
            submiter.name =  this.name;
            submiter.value = this.value;
        };

        BX.bind(children[i], 'click', cb);
        BX.bind(children[i], 'tap', cb);
    }/**/
    if (BI.customFilter) {
        for (var key in BI.customFilter) {
            console.log(key);
            var control = form['bpriact_' + key];
            if (!control)
                control = form['bprioact_' + key];
            if (control.tagName == 'INPUT') {
                control = $('#bpriact_' + key + '_control_').find('div[data-name="bpriact_' + key+'"]');
                BI.filterNewSelectValues(control, BI.customFilter[key]['VALUES']);
            } else
                BI.filterSelectValues(control, BI.customFilter[key]['VALUES']);
        }
    }
});



if (typeof BI.RusirTableControllerCommon == 'undefined') {
    BI.SCSGCurrencyList = {};
    BI.iblockLookup = {};
    BI.RusirTableControllerCommon = function (tableElement, ajaxId, entityTypeId, entityId, columns, rows, options) {

        this.node = $(tableElement);
        this.saveBtn = null;
        this.ajaxId = ajaxId;
        this.entityTypeId = entityTypeId;
        this.entityId = entityId;
        this.columns = columns;
        this.callbacks = {};
        this.tmpRowId = -1;
        this.DETAIL_PICTURES = [];
        this.clickTimeout = null;
        this.dblClickBlock = false;
        this.rowsToDelete = [];
        if (typeof options == 'undefined')
            options = {};
        this.readOnly = !!options.readOnly;
        this.noAddDelete = !!options.noAddDelete;
        this.noSaveButton = !!options.noSaveButton;
        this.srcWH = options.srcWH;
        this.dstWH = options.dstWH;
        this.pcgTbl = options.packageTable;
        this.dnd = options.dnd;
        if (typeof rows == 'undefined' || rows == null || $.isEmptyObject(rows))
            this.rows = [];
        else
            this.rows = rows;

        for(column in columns)
        {

            if(columns[column].CODE=='PRIMEECHANIE_FAYL')
            {
                columns[column].editor=false
                columns[column].field="DETAIL_PICTURE"
                columns[column].formatter="html"
                columns[column].formatterClipboard="noCopy"
                columns[column].editable= true

            }
        }

        this.init();

    };

    BI.RusirTableControllerCommon.prototype = {
        mutateCurrency: function(value, data, type, params, component) {
            value = "" + value;
            if (value === "")
                return value;
            let res;
            if (value.includes(',') && value.includes('.'))
                res = parseFloat(value.replace(/[^0-9.]/g, ''));
            else if (value.includes(','))
                res = parseFloat(value.replace(',', '.').replace(/[^0-9.]/g, ''));
            else
                res = parseFloat(value.replace(/[^0-9.]/g, ''));

            if (isNaN(res))
                return '';
            return res;
        },

        hackSelection: function(el) {

            let selection = window.getSelection();
            let range = document.createRange();
            range.selectNodeContents(el);

            range.collapse();
            selection.removeAllRanges();
            selection.addRange(range);
            this.clickTimeout = null;
        },

        checkEditable: function(cell) {
            if (this.dblClickBlock || this.table.modules.edit.currentCell) {
                if (this.clickTimeout) {
                    console.log('Undo Hack selection');
                    clearTimeout(this.clickTimeout);
                    this.clickTimeout = null;
                }

                if (window.event && window.event.type == "keydown" && window.event.key == "Tab")
                    this.nextEditAllowed = true;
                return true;
            }
            if (window.event && window.event.type == "keydown" && window.event.key == "Tab" && this.nextEditAllowed) {
                this.nextEditAllowed = false;
                return true;
            }
            this.nextEditAllowed = false;

            let el = cell.getTable().element;
            if (!this.clickTimeout) {

                if(this.deleteBtn)
                    this.deleteBtn.attr("disabled", false);
                console.log('Set Hack selection');
                this.clickTimeout = setTimeout(this.hackSelection.bind(this, el), 300);

            }
            return false;
        },

        cellDblClick: function (e, cell) {
            if (!cell.getColumn().getDefinition().editor)
                return;
            if (this.clickTimeout) {
                console.log('Undo Hack selection1');

                clearTimeout(this.clickTimeout);
                this.clickTimeout = null;
            }
            this.dblClickBlock = true;
            cell.edit(true);
            this.dblClickBlock = false;
        },

        init: function() {
            for (let col of this.columns) {
                if (this.readOnly) {
                    col.editable = false;
                } else {
                    col.editable = this.checkEditable.bind(this);
                    col.cellDblClick = this.cellDblClick.bind(this);
                }
                if (col.formatter === 'lookup') {
                    if (col.hasOwnProperty('editorParams') && col.editorParams.hasOwnProperty('multiselect') && col.editorParams.multiselect) {
                        delete col.formatterParams[""];
                        col.formatter = function(cell, formatterParams, onRendered) {
                            let val = cell.getValue();
                            let res = '';
                            if (typeof val == 'undefined')
                                res = '';
                            else if (Array.isArray(val))
                                res = val.map(i => formatterParams.hasOwnProperty(i)?formatterParams[i]:null).filter(i => i != null).join('+');
                            else
                                res = val;
                            return document.createTextNode(res);
                        }
                        col.mutatorClipboard = function (value, data, type, params, column) {
                            let def = column.getDefinition();
                            // let re = new RegExp('^' + value.slice(0, 1), 'i');
                            // return Object.keys(def.formatterParams).find(key => re.test(def.formatterParams[key]));

                            return value.split('+').map(function(v) {
                                return Object.keys(def.formatterParams).find(key => def.formatterParams[key] == v.trim() )
                            }).filter(i => i != undefined);
                        }
                        col.accessorClipboard = function(value, data, type, params, column) {
                            let def = column.getDefinition();
                            if (Array.isArray(value)) {
                                return value.map(i => def.formatterParams.hasOwnProperty(i)?def.formatterParams[i]:null).filter(i => i != null).join('+');
                            } else
                                return value;
                        };
                    } else {
                        col.formatterParams[null] = col.formatterParams[""];
                        col.mutatorClipboard = function (value, data, type, params, column) {
                            let def = column.getDefinition();
                            let re = new RegExp('^' + value.slice(0, 1), 'i');
                            return Object.keys(def.formatterParams).find(key => re.test(def.formatterParams[key]));
                        }
                    }
                } else if (col.formatter === 'money') {
                    // col.mutatorClipboard = this.mutateCurrency.bind(this);
                    col.mutator = this.mutateCurrency.bind(this);
                } else if (col.formatter === 'datetime') {
                    col.editor = this.dateEditor;
                } else if (col.formatter === 'link') {
                    col.formatterParams.url = function(cell) {
                        return /*cell.getColumn().getDefinition().formatterParams.urlPrefix + */cell.getValue() + '/';
                    };
                } else if (col.formatter === 'iblock') {
                    col.formatter = this.iblockFormatter;
                } else if (col.formatter === 'product') {
                    col.formatter = this.productFormatter;
                }
                if (col.editor === 'select' && col.editorParams.values.hasOwnProperty('')) {
                    col.editorParams.values[null] = col.editorParams.values[''];
                    delete col.editorParams.values[''];
                } else if (col.editor === 'number') {
                    col.editor = this.numberEditor;
                } else if (col.editor === 'iblock') {
                    col.editor = this.iblockEditor;
                } else if (col.editor === 'product') {
                    col.editor = this.productEditor;
                }
                if (col.formatterClipboard == 'noCopy') {
                    delete col.formatterClipboard;
                    col.accessorClipboard = function (value, data, type, params, column) {
                        return "";
                    };
                }
            }

            this.btnContainer = $('<div/>').appendTo(this.node);
            if (!this.readOnly) {
                this.smallBtnContainer = $('<div class="ui-btn-toolbar" style="width: 100%"/>').insertAfter(this.node);
                if (!this.noAddDelete) {
                    this.addBtn = $('<button type="button" class="ui-btn ui-btn-sm ui-btn-light-border">+</button>')
                        .attr('id', this.node.attr('id') + '-add-btn')
                        .appendTo(this.smallBtnContainer);
                    this.deleteBtn = $('<button type="button" class="ui-btn ui-btn-sm ui-btn-danger" disabled="disabled">-</button>')
                        .attr('id', this.node.attr('id') + '-del-btn')
                        .insertAfter(this.addBtn);
                }
                // this.currencySelect = $('<select class="bi-input-control"></select>')
                //     .attr('id', this.node.attr('id') + '-currency-select');
                console.log(this.noSaveButton)
                if(!this.noSaveButton) {
                    this.saveBtn = $('<button type="button" class="ui-btn ui-btn-primary">Сохранить</button>')
                        .attr('id', this.node.attr('id') + '-save-btn')
                        .appendTo(this.smallBtnContainer);

                    this.applyBtn = $('<button type="button" class="ui-btn ui-btn-success">Провести</button>')
                        .attr('id', this.node.attr('id') + '-apply-btn')
                        .appendTo(this.smallBtnContainer);
                }


                let controlCreated = false;
                for (let col of this.columns)
                    if (col.formatter == 'money') {
                        if (!controlCreated) {
                            for (let currCode in BI.SCSGCurrencyList) {
                                this.currencySelect.append(
                                    $('<option></option>')
                                        .text(BI.SCSGCurrencyList[currCode].NAME)
                                        .attr('value', currCode)
                                        .attr('selected', currCode == col.CURRENCY)
                                );
                            }
                            $('<span style="width: 300px; margin: 3px;"></span>')
                                .append('Валюта:')
                                .append(this.currencySelect)
                                .insertAfter(this.deleteBtn);
                            this.currencySelect.on('change', this.onCurrencyChange.bind(this));
                            this.savedFormatterParams = col.formatterParams;
                            controlCreated = true;
                        }
                        col.bottomCalcFormatterParams = col.formatterParams = this.getMoneyFormatterParams.bind(this);
                    }
            }

            let tableOptions = {
                // height: "300px",
                // minHeight: "205px",
                // maxHeight: "800px",
                index: 'ID',
                clipboardCopyRowRange : 'selected',
                // columnMinWidth : 90,
                data: this.rows, //assign data to table
                layout: "fitColumns", //fit columns to width of table (optional)
                // resizableRows: true,
                virtualDom: true,
                selectable: true,
                selectableRangeMode:"click",
                headerFilterPlaceholder:"фильтр...",
                clipboard: true,

                clipboardCopyStyled:false,
                clipboardPasteAction: this.onClipboardPaste.bind(this),
                clipboardCopyConfig: {
                    columnHeaders: false, //do not include column headers in clipboard output
                    columnGroups: false, //do not include column groups in column headers for printed table
                    rowGroups: false, //do not include row groups in clipboard output
                    columnCalcs: false, //do not include column calculation rows in clipboard output
                    dataTree: false, //do not include data tree in printed table
                    formatCells: true, //show raw cell values without formatter
                },
                clipboardCopyFormatter:function(type, output){
                    //type - a string representing the type of the content, either "plain" or "html"
                    //output - the output string about to be passed to the clipboard
                    return output;
                },
                columns: this.columns
            };
            if (!this.readOnly) {
                tableOptions.dataChanged = this.onDataChanged.bind(this);
                tableOptions.rowSelectionChanged = this.onSelectionChanged.bind(this);
                tableOptions.clipboardPasted = this.onClipboardPasted.bind(this);
                tableOptions.cellEdited = this.onCellEdited.bind(this);
            }
            if (this.dnd) {
                tableOptions.movableRows = true;
                tableOptions.movableRowsConnectedTables = this.dnd;
                tableOptions.movableRowsSender = "delete";
                let sendEvent = (arg) => { if (this.callbacks.hasOwnProperty('change')) this.callbacks.change(arg); }
                tableOptions.movableRowsReceived = sendEvent;
                tableOptions.movableRowsSent = sendEvent;
                tableOptions.columns.unshift({ rowHandle:true, formatter:"handle", headerSort:false, frozen:true, width:30, minWidth:30});
            }

            console.log('tableOptions');
            console.log(tableOptions);

            this.table = new Tabulator(this.node[0], tableOptions);

            //this.node.on('keydown', 'input,select', this.onEditorKeydown.bind(this));

            if (!this.readOnly) {
                this.bindEventHandlers();
                this.initDragAndDrop();
            }
        },

        initDragAndDrop: function() {
            this.node.on('dragenter', 'div.bi-cell-image-wrapper', this.onImgDragEnter.bind(this));
            this.node.on('dragover', 'div.bi-cell-image-wrapper', this.onImgDragEnter.bind(this));
            this.node.on('dragleave', 'div.bi-cell-image-wrapper', this.onImgDragLeave.bind(this));
            this.node.on('drop', 'div.bi-cell-image-wrapper', this.onImgDrop.bind(this));
        },

        onImgDragEnter: function (e) {
            let el = $(e.currentTarget);
            if (el.hasClass('bi-cell-image-wrapper')) {
                let dt = e.originalEvent.dataTransfer;
                // let files = dt.files.length;
                // let items = dt.items.length;

                // if (e.type == 'dragenter') {

                //     for (let it of dt.files)

                // }
                if ($.inArray('Files', dt.types) >= 0 /*&& dt.items.length == 1 && /^image\//.test(dt.items[0].type)*/) {
                    el.addClass('active');
                    e.originalEvent.dataTransfer.dropEffect = 'copy';
                    e.preventDefault();
                    e.stopPropagation();
                } else {
                    e.originalEvent.dataTransfer.dropEffect = 'none';
                    $(e.currentTarget).addClass('forbidden');
                    e.preventDefault();
                    e.stopPropagation();
                }
            } else {
                e.originalEvent.dataTransfer.dropEffect = 'none';
                e.preventDefault();
            }
        },

        onImgDragLeave: function (e) {
            let el = $(e.currentTarget);
            if (el.hasClass('bi-cell-image-wrapper')) {
                $(e.currentTarget).removeClass('active');
                $(e.currentTarget).removeClass('forbidden');
                return false;
            } else
                e.preventDefault();
        },

        onImgDrop: function (e) {
            let el = $(e.currentTarget);
            if (el.hasClass('bi-cell-image-wrapper')) {
                el.removeClass('active');
                let dt = e.originalEvent.dataTransfer;
                if ($.inArray('Files', dt.types) >= 0 && dt.items.length == 1 && /^image\//.test(dt.items[0].type)) {
                    el.addClass('changed');
                    this.markChanged();
                    let file = dt.files[0];
                    //this.table.updateData([{ID: el.data('elementId'), DETAIL_PICTURE_FILE: file}]);
                    this.DETAIL_PICTURES[el.data('elementId')] = file;
                    let reader = new FileReader()
                    reader.readAsDataURL(file)

                    reader.onloadend = function() {
                        let img = el.find('img');
                        if (!img.length) {
                            img = $('<img class="bi-cell-image" height="30px" src="" alt="0"/>');
                            img.attr('src', reader.result);
                            img.appendTo(el);
                        } else
                            img.attr('src', reader.result);
                    }
                }
                return false;
            } else
                e.preventDefault();
        },

        dateEditor: function(cell, onRendered, success, cancel, editorParams) {
            //cell - the cell component for the editable cell
            //onRendered - function to call when the editor has been rendered
            //success - function to call to pass the successfuly updated value to Tabulator
            //cancel - function to call to abort the edit and return to a normal cell
            //editorParams - params object passed into the editorParams column definition property

            //create and style editor
            let editor = document.createElement("input");

            editor.setAttribute("type", "date");

            //create and style input
            editor.style.padding = "3px";
            editor.style.width = "100%";
            editor.style.boxSizing = "border-box";

            //Set value of editor to the current value of the cell
            editor.value = moment(cell.getValue(), "DD.MM.YYYY").format("YYYY-MM-DD")

            //set focus on the select box when the editor is selected (timeout allows for editor to be added to DOM)
            onRendered(function(){
                editor.focus();
                editor.style.css = "100%";
            });

            //when the value has been set, trigger the cell to update
            function successFunc(){
                success(moment(editor.value, "YYYY-MM-DD").format("DD.MM.YYYY"));
            }

            //editor.addEventListener("change", successFunc);
            editor.addEventListener("blur", successFunc);
            editor.addEventListener("keypress", function(e) { if (e.keyCode === 13) { successFunc(); }});

            //return the editor element
            return editor;
        },

        numberEditor: function(cell, onRendered, success, cancel, editorParams){
            //cell - the cell component for the editable cell
            //onRendered - function to call when the editor has been rendered
            //success - function to call to pass the successfuly updated value to Tabulator
            //cancel - function to call to abort the edit and return to a normal cell
            //editorParams - params object passed into the editorParams column definition property

            //create and style editor
            var editor = document.createElement("input");

            editor.setAttribute("type", "text");
            console.log('ss2')
            //create and style input
            editor.style.padding = "3px";
            editor.style.width = "100%";
            editor.style.boxSizing = "border-box";

            //Set value of editor to the current value of the cell
            if (cell.getValue())
                editor.value = cell.getValue();
            else
                editor.value = '';

            //set focus on the select box when the editor is selected (timeout allows for editor to be added to DOM)
            onRendered(function(){
                editor.focus();
                editor.style.css = "100%";
            });

            //when the value has been set, trigger the cell to update
            function successFunc(){
                success(editor.value);
            }

            function inputFunc(e) {
                if (e.target.value) {
                    if (e.target.value.includes(',') && e.target.value.includes('.'))
                        e.target.value = parseFloat(e.target.value.replace(/[^0-9.]/g, ''));
                    else if (e.target.value.includes(','))
                        e.target.value = parseFloat(e.target.value.replace(',', '.').replace(/[^0-9.]/g, ''));
                    else
                        e.target.value = e.target.value.replace(/[^0-9.]/, '');
                }
            }

            editor.addEventListener("change", successFunc);
            editor.addEventListener("blur", successFunc);
            editor.addEventListener("input", inputFunc);
            console.log('ss3')
            //return the editor element
            return editor;
        },

        iblockFormatter: function(cell, formatterParams, onRendered) {
            let id = cell.getValue();
            if (!id || !formatterParams.IBLOCK_ID || !BI.iblockLookup.hasOwnProperty(formatterParams.IBLOCK_ID))
                return '';
            return BI.iblockLookup[formatterParams.IBLOCK_ID][id];
        },

        iblockEditor: function(cell, onRendered, success, cancel, editorParams){
            //cell - the cell component for the editable cell
            //onRendered - function to call when the editor has been rendered
            //success - function to call to pass the successfuly updated value to Tabulator
            //cancel - function to call to abort the edit and return to a normal cell
            //editorParams - params object passed into the editorParams column definition property

            //create and style editor
            let editor = document.createElement("input");
            let SEARCH = null;
            let __search_current_row = null;

            editor.setAttribute("type", "text");
            console.log('ss4')
            //create and style input
            editor.style.padding = "3px";
            editor.style.width = "100%";
            editor.style.boxSizing = "border-box";

            //Set value of editor to the current value of the cell
            if (cell.getValue())
                editor.value = BI.iblockLookup[editorParams.IBLOCK_ID][cell.getValue()];
            else
                editor.value = '';

            //set focus on the select box when the editor is selected (timeout allows for editor to be added to DOM)
            onRendered(function(){
                editor.focus();
                editor.style.css = "100%";
            });

            //when the value has been set, trigger the cell to update
            function successFunc(value, text){
                // $(document).off('click', closeSearch);
                BI.iblockLookup[editorParams.IBLOCK_ID][value] = text;
                success(value);
            }

            function closeSearch(event) {
                if (SEARCH)
                    SEARCH.style.display = 'none';
                event.stopPropagation();
                if (__search_current_row)
                    successFunc(__search_current_row.BX_ROW_DATA.ID, __search_current_row.BX_ROW_DATA.NAME);
                else {
                    // $(document).off('click', closeSearch);
                    cancel();
                }
            }

            // $(document).on('click', closeSearch);
            editor.onclick = function(e) {e.stopPropagation()};

            function searchFunc() {
                if (editor.value) {
                    let data = {
                        sessid: BX.message('bitrix_sessid'),
                        MODE: 'SEARCH',
                        search: editor.value,
                        IBLOCK_ID: editorParams.IBLOCK_ID,
                        WITHOUT_IBLOCK: 'N',
                        lang: 'ru',
                        site: 's1',
                        admin: 'N',
                        TYPE: 'ELEMENT',
                        RESULT_COUNT: 20,
                        BAN_SYM: ',;',
                        REP_SYM: ' ',
                    };
                    $.ajax({
                        type: 'GET',
                        url: '/bitrix/components/bitrix/main.lookup.input/templates/iblockedit/ajax.php',
                        data: data
                    }).then(function(DATA){
                        if (DATA.length > 0) {

                            if (DATA.length == 1 && null != DATA[0].READY)
                            {
                                if (SEARCH)
                                    SEARCH.style.display = 'none';
                                successFunc(DATA[0].ID, DATA[0].NAME);
                                return;
                            }

                            if (null == SEARCH) {
                                SEARCH = BX.GetDocElement().appendChild(document.createElement('DIV'));
                                SEARCH.className = 'mli-search-results';
                                SEARCH.style.position = 'absolute';
                                SEARCH.style.zIndex = 400;
                            }

                            let pos = BX.pos(editor, false);

                            SEARCH.style.top = pos.bottom + 'px';
                            SEARCH.style.left = pos.left + 'px';
                            SEARCH.style.width = (pos.right - pos.left - 2) + 'px';

                            for (let i = 0; i < DATA.length; i++) {
                                let obSearchResult = SEARCH.appendChild(document.createElement('DIV'));
                                let name = DATA[i].NAME;
                                let id = DATA[i].ID;
                                obSearchResult.className = 'mli-search-result';
                                obSearchResult.appendChild(document.createTextNode(name + ' [' + id + ']'));

                                obSearchResult.BX_ROW_DATA = DATA[i];
                                obSearchResult.onclick = closeSearch;
                                obSearchResult.onmouseover = function(){
                                    if (null != __search_current_row)
                                        __search_current_row.className = 'mli-search-result';

                                    __search_current_row = this;
                                    this.className = 'mli-search-result mli-search-current';
                                };
                                BX.bind(obSearchResult, "mousedown", function (event) {
                                    event.stopPropagation();
                                });
                            }

                            SEARCH.style.display = 'block';
                        } else {
                            SEARCH.style.display = 'none';
                        }
                    });
                }

            }

            let tm = null;
            function inputFunc() {
                if (tm)
                    clearTimeout(tm);
                tm = setTimeout(searchFunc, 1000);
            }

            // editor.addEventListener("change", successFunc);
            editor.addEventListener("blur", closeSearch);
            editor.addEventListener("input", inputFunc);
            console.log('ss2')
            //return the editor element
            return editor;
        },

        productFormatter: function(cell, formatterParams, onRendered) {
            return cell.getRow().getData()['NAME'];
        },

        productEditor: function(cell, onRendered, success, cancel, editorParams){
            //cell - the cell component for the editable cell
            //onRendered - function to call when the editor has been rendered
            //success - function to call to pass the successfuly updated value to Tabulator
            //cancel - function to call to abort the edit and return to a normal cell
            //editorParams - params object passed into the editorParams column definition property

            //create and style editor
            let editor = document.createElement("input");
            let SEARCH = null;
            let __search_current_row = null;
            let row = cell.getRow();

            editor.setAttribute("type", "text");
            console.log('ss2')
            //create and style input
            editor.style.padding = "3px";
            editor.style.width = "100%";
            editor.style.boxSizing = "border-box";

            //Set value of editor to the current value of the cell
            // if (cell.getValue())
            //     editor.value = BI.iblockLookup[editorParams.IBLOCK_ID][cell.getValue()];
            // else
            //     editor.value = '';
            let rowData = row.getData();
            if (rowData.hasOwnProperty('NAME'))
                editor.value = row.getData().NAME;
            else
                editor.value = '';

            //set focus on the select box when the editor is selected (timeout allows for editor to be added to DOM)
            onRendered(function(){
                editor.focus();
                editor.style.css = "100%";
            });

            //when the value has been set, trigger the cell to update
            function successFunc(value, text, measure){
                // $(document).off('click', closeSearch);
                if (value)
                    BI.iblockLookup[editorParams.IBLOCK_ID][value] = text;
                rowData['NAME'] = text;
                if (measure && editorParams.measureField)
                    rowData[editorParams.measureField] = measure;
                row.update(rowData);
                success(value);
            }

            function closeSearch(event) {
                if (SEARCH)
                    SEARCH.style.display = 'none';
                event.stopPropagation();
                if (__search_current_row)
                    successFunc(__search_current_row.BX_ROW_DATA.ID, __search_current_row.BX_ROW_DATA.NAME, __search_current_row.BX_ROW_DATA.MEASURE);
                else {
                    // $(document).off('click', closeSearch);
                    //cancel();
                    successFunc(0, editor.value);
                }
            }

            // $(document).on('click', closeSearch);
            editor.onclick = function(e) {e.stopPropagation()};

            function searchFunc() {
                if (SEARCH)
                    $(SEARCH).empty();
                if (editor.value) {
                    let data = {
                        sessid: BX.message('bitrix_sessid'),
                        ACTION: 'searchProduct',
                        search: editor.value,
                        IBLOCK_ID: editorParams.IBLOCK_ID,
                        WITHOUT_IBLOCK: 'N',
                        lang: 'ru',
                        site: 's1',
                        admin: 'N',
                        TYPE: 'ELEMENT',
                        RESULT_COUNT: 20,
                        BAN_SYM: ',;',
                        REP_SYM: ' ',
                    };

                    $.ajax({
                        type: 'POST',
                        url: '/local/components/b-integration/rusir.dynamic.tab/ajax.php',
                        data: data
                    }).then(function(DATA){
                        if (DATA.length > 0) {

                            // if (DATA.length == 1 && null != DATA[0].READY)
                            // {
                            //     if (SEARCH)
                            //         SEARCH.style.display = 'none';
                            //     successFunc(DATA[0].ID, DATA[0].NAME);
                            //     return;
                            // }

                            if (null == SEARCH) {
                                SEARCH = BX.GetDocElement().appendChild(document.createElement('DIV'));
                                SEARCH.className = 'mli-search-results';
                                SEARCH.style.position = 'absolute';
                                SEARCH.style.zIndex = 400;
                            }

                            let pos = BX.pos(editor, false);

                            SEARCH.style.top = pos.bottom + 'px';
                            SEARCH.style.left = pos.left + 'px';
                            SEARCH.style.width = (pos.right - pos.left - 2) + 'px';

                            for (let i = 0; i < DATA.length; i++) {
                                let obSearchResult = SEARCH.appendChild(document.createElement('DIV'));
                                let name = DATA[i].NAME;
                                let id = DATA[i].ID;
                                obSearchResult.className = 'mli-search-result';
                                obSearchResult.appendChild(document.createTextNode(name + ' [' + id + ']'));

                                obSearchResult.BX_ROW_DATA = DATA[i];
                                obSearchResult.onclick = closeSearch;
                                obSearchResult.onmouseover = function(){
                                    if (null != __search_current_row)
                                        __search_current_row.className = 'mli-search-result';

                                    __search_current_row = this;
                                    this.className = 'mli-search-result mli-search-current';
                                };
                                BX.bind(obSearchResult, "mousedown", function (event) {
                                    event.stopPropagation();
                                });
                            }

                            SEARCH.style.display = 'block';
                        } else if (SEARCH)
                            SEARCH.style.display = 'none';
                    });
                }

            }

            let tm = null;
            function inputFunc() {  console.log('ssq')
                if (tm)
                    clearTimeout(tm);
                tm = setTimeout(searchFunc, 1000);
            }
            tm = setTimeout(searchFunc, 100);

            // editor.addEventListener("change", successFunc);
            editor.addEventListener("blur", closeSearch);
            editor.addEventListener("input", inputFunc);
            console.log('ssw')
            //return the editor element
            return editor;
        },

        getMoneyFormatterParams: function(cell)
        {
            return this.savedFormatterParams;
        },

        onCurrencyChange: function() {
            this.savedFormatterParams = BI.SCSGCurrencyList[this.currencySelect.val()];
            this.markChanged();
            this.table.redraw(true);
        },

        bindEventHandlers: function() {
            // $('.bp-button-accept').on('click', this.onSaveBtn.bind(this));
            if(this.saveBtn)
                this.saveBtn.on('click', this.onSaveBtn.bind(this));
            if(this.applyBtn)
                this.applyBtn.on('click', this.onApplyBtn.bind(this));

            if (!this.noAddDelete) {
                this.addBtn.on('click', this.onAddBtn.bind(this));
                this.deleteBtn.on('click', this.onDeleteBtn.bind(this));
            }
            /*$('#table-copy-btn').on('click', function() {
                this.table.copyToClipboard("selected");
            });
            $('body').on('keyup', function(e){
                if (e.ctrlKey && e.keyCode == 67) {
                    e.preventDefault();
                    this.table.copyToClipboard("selected");
                }
            });*/
        },

        convertModelToFormData: function(val, formData = new FormData, namespace = '') {
            if ((typeof val !== 'undefined') && val !== null) {
                if (val instanceof Date) {
                    formData.append(namespace, val.toISOString());
                } else if (val instanceof Array) {
                    for (let i = 0; i < val.length; i++) {
                        this.convertModelToFormData(val[i], formData, namespace + '[' + i + ']');
                    }
                } else if (typeof val === 'object' && !(val instanceof File)) {
                    for (let propertyName in val) {
                        if (val.hasOwnProperty(propertyName)) {
                            this.convertModelToFormData(val[propertyName], formData, namespace ? `${namespace}[${propertyName}]` : propertyName);
                        }
                    }
                } else if (val instanceof File) {
                    formData.append(namespace, val);
                } else {
                    formData.append(namespace, val.toString());
                }
            }
            return formData;
        },

        onSaveBtn: function () {
            console.log('onSaveBtn');
            let self = this;
            let rows = self.table.getRows();
            let packageData = {};
            let data = this.getData();
            // debugger;
            let FD = new FormData();
            FD.append('sessid', BX.message('bitrix_sessid'));
            console.log(this.getData())
            // FD.append('elements[]', rows);
            data.forEach((obj, i) => {
                // Object.entries(obj).forEach(item => {
                //     FD.append(`elements[${i}][${item[0]}]`, item[1]);
                // })
                Object.entries(obj).forEach(([index, value]) => {
                    FD.append(`elements[${i}][${index}]`, value);
                })
            });
            FD.append('entityID', this.entityId);
            FD.append('src', self.srcWH);
            FD.append('destination', self.dstWH);
            FD.append('assigned', $(`select[name='RESPONSIBLE']`).val());

//             if(self.pcgTbl.length) {
//                 let f = self.pcgTbl.find('input#file_input_UF_CRM_13_PCG_MEDIA_uploader')
//                 FD.append('packageData[TRANSPORT_INFO]', self.pcgTbl.find("td[data-field=transport_data1]").text());
//                 FD.append('packageData[USER_COMMENT]', self.pcgTbl.find("td[data-field=transport_data2]").text());
//                 FD.append('packageData[TRANSPORT_TYPE]', self.pcgTbl.find("select[name=transport_data3]").val());
//                 FD.append('packageData[WEIGHT]', self.pcgTbl.find("td[data-field=transport_data4]").text());
//                 FD.append('packageData[MEDIA_S][]', self.pcgTbl.find('input#file_input_mfiUF_CRM_13_PCG_MEDIA').val());
//
//                 const fileInput = document.getElementById('attach');
//
// // Append each file to the same key
//                 if(fileInput) {
//                     for (let i = 0; i < fileInput.files.length; i++) {
//                         FD.append(`packageData[MEDIA][${i}]`, fileInput.files[i]); // Use the same key 'files' for each file
//                     }
//                 }
//
//                 // packageData['TRANSPORT_INFO'] = self.pcgTbl.find("td[data-field=transport_data1]").text()
//                 // packageData['USER_COMMENT'] = self.pcgTbl.find("td[data-field=transport_data2]").text()
//                 // packageData['TRANSPORT_TYPE'] = self.pcgTbl.find("select[name=transport_data3]").val()
//                 // packageData['WEIGHT'] = self.pcgTbl.find("td[data-field=transport_data4]").text()
//             }
            BX.ajax.runComponentAction('b-integration:rusir.warehouse.translocation',
                'saveBeforeDistribute', { // Вызывается без постфикса Action
                    mode: 'class',
                    // data: {
                    //     sessid: BX.message('bitrix_sessid'),
                    //     elements: this.getData(),
                    //     entityID: this.entityId,
                    //     src: self.srcWH,
                    //     destination: self.dstWH,
                    //     packageData: 1
                    //
                    // }, // ключи объекта data соответствуют параметрам метода
                    data: FD
                })
                .then(function(response) {
                    console.log(response)
                    if (response.status === 'success') {
                        let rows = self.table.getRows();
                        if(response.data.errors) {
                            for (const error of response.data.errors) {
                                BX.UI.Notification.Center.notify({
                                    content: error,

                                    actions: [
                                        {
                                            title: "Закрыть",
                                            events: {
                                                click: function(event, balloon, action) {
                                                    balloon.close()
                                                }
                                            }
                                        }
                                    ]
                                })
                            }
                        }
                        if('afterCommit' in self.callbacks)
                            // if (self.callbacks.hasOwnProperty('afterCommit'))
                            self.callbacks.afterCommit(response.data.data);
                    }
                });
        },
        onApplyBtn: function () {
            console.log('onApplyBtn');
            let self = this;
            let rows = self.table.getRows();
            let type = this.node.prop('id').split("-");
            let packageData = {};
            if('beforeCommit' in self.callbacks)
                // if (self.callbacks.hasOwnProperty('afterCommit'))
                self.callbacks.beforeCommit(rows);
            // if(self.pcgTbl.length) {
            //     packageData['TRANSPORT_INFO'] = self.pcgTbl.find("td[data-field=transport_data1]").text()
            //     packageData['USER_COMMENT'] = self.pcgTbl.find("td[data-field=transport_data2]").text()
            //     packageData['TRANSPORT_TYPE'] = self.pcgTbl.find("select[name=transport_data3]").val()
            //     packageData['WEIGHT'] = self.pcgTbl.find("td[data-field=transport_data4]").text()
            // }
            BX.ajax.runComponentAction('b-integration:rusir.warehouse.translocation',
                'commitDistribute', { // Вызывается без постфикса Action
                    mode: 'class',
                    data: {
                        sessid: BX.message('bitrix_sessid'),
                        entityID: this.entityId,
                        elements: this.getData(),
                        src: self.srcWH,
                        destination: self.dstWH,
                        assigned: $(`select[name='RESPONSIBLE']`).val(),
                        packageData: self?.pcgTbl ? 1 : 0
                    }, // ключи объекта data соответствуют параметрам метода
                })
                .then(function(response) {
                    console.log(response)
                    if (response.status === 'success') {
                        let rows = self.table.getRows();
                        if(response.data.errors) {
                            for (const error of response.data.errors) {
                                BX.UI.Notification.Center.notify({
                                    content: error,

                                    actions: [
                                        {
                                            title: "Закрыть",
                                            events: {
                                                click: function(event, balloon, action) {
                                                    balloon.close()
                                                }
                                            }
                                        }
                                    ]
                                })
                            }
                        }
                        console.log(self.callbacks);
                        if('afterCommit' in self.callbacks)
                            // if (self.callbacks.hasOwnProperty('afterCommit'))
                            self.callbacks.afterCommit(response.data.data);
                    }
                });
        },

        onAddBtn: function()
        {
            let rowData = this.table.getData();
            if(rowData.length==0) {

                btn = $('.bp-button-accept')
                btn = btn[0]
                console.log(btn)
                btn.style.pointerEvents = 'auto';
                btn.style.opacity = '1';
            }

            if(this.entityTypeId==149){
                console.log('self.entityId')
                console.log(this.entityId)
                BX.ajax({
                    url: "/local/components/b-integration/rusir.dynamic.tab/ajax.php",
                    method: 'POST',
                    data: {
                        sessid: BX.message('bitrix_sessid'),
                        ACTION: 'get_category',
                        //   elements: new_subissue,
                        field:['UF_CRM_1_1630993180283'],
                        ENTITY_TYPE_ID: 149,
                        ENTITY_ID: this.entityId,
                    },
                    dataType: 'json',
                    onsuccess: (data) => {
                        console.log(data)
                        let row = {ID: this.tmpRowId--,d:'11111'};

                        if (this.callbacks.hasOwnProperty('before_add_row'))
                            this.callbacks.before_add_row(row);
                        this.table.addData([row]);
                    }
                })

            }else{
                let row = {ID: this.tmpRowId--};
                for (let col of this.columns)
                    if (col.field == 'DETAIL_PICTURE')
                        row.DETAIL_PICTURE = '<div class="bi-cell-image-wrapper" data-element-id="'+row.ID+'"/>';
                if (this.callbacks.hasOwnProperty('before_add_row'))
                    this.callbacks.before_add_row(row);
                this.table.addData([row]);
                console.log(' this.table.addData')
                console.log(  row)
            }

        },

        onDeleteBtn: function()
        {


            let rows = this.table.getSelectedRows();

            for (let row of rows) {
                if (row.getIndex() > 0){
                    this.rowsToDelete.push(row.getIndex());

                }

                row.delete();

            }
        },

        markChanged: function ()
        {
            if (this.saveBtn)
                this.saveBtn.attr('disabled', false);
        },

        onDataChanged: function(data)
        {
            console.log(data)
            if (this.changeAntiLoop)
                return;
            this.changeAntiLoop = true;
            if (this.callbacks.hasOwnProperty('change'))
                this.callbacks.change(data);
            this.markChanged();
            this.changeAntiLoop = false;
        },

        onSelectionChanged: function(data, rows) {
            console.log('click')
            if (this.deleteBtn)
                this.deleteBtn.attr("disabled", rows.length == 0);
            if (this.callbacks.hasOwnProperty('selection_changed'))
                this.callbacks.selection_changed(data, rows);

        },

        onClipboardPaste: function(rowData){
            let i = rowData.length - 1;
            let empty = true;
            for (let c in rowData[i])
                if (rowData[i].hasOwnProperty(c) && rowData[i][c] != "") {
                    empty = false;
                    break;
                }
            if (empty)
                rowData.splice(i, 1);
            for (let row of rowData) {
                row.ID = this.tmpRowId--;
                for (let col of this.columns) {
                    if (col.editor == this.numberEditor && row.hasOwnProperty(col.field))
                        row[col.field] = row[col.field].replace(/[^0-9,.]/, '').replace(/,/, '.');
                    else if (col.editor == 'product') {
                        row.NAME = row[col.field];
                        row[col.field] = 0;
                    }
                    if (col.field == 'DETAIL_PICTURE')
                        row.DETAIL_PICTURE = '<div class="bi-cell-image-wrapper" data-element-id="'+row.ID+'"/>';
                }
            }
            return this.table.setData(rowData);
        },

        onClipboardPasted: function(clipboard, rowData, rows) {
            if (this.callbacks.hasOwnProperty('change'))
                this.callbacks.change(rowData);
            this.markChanged();
        },

        onCellEdited: function(cell){

            if (cell.getField() == 'PROPERTY_97' || cell.getField() == 'PROPERTY_98') {
                let sum = cell.getRow().getCell('PROPERTY_97').getValue() * cell.getRow().getCell('PROPERTY_98').getValue();
                if (!isNaN(sum))
                    cell.getRow().getCell('PROPERTY_99').setValue(sum.toFixed(2));
            }
            if(cell.getField() == 'ACCEPT') {
                let ttl = Number(cell.getRow().getCell('QUANTITY').getValue());
                let accepted = Number(cell.getRow().getCell('ACCEPTED').getValue());
                let diff = ttl - accepted;
                let accept = Number(cell.getRow().getCell('ACCEPT').getValue());
                if(accept > diff)
                    alert("Попытка распределить больше доступного кол-ва!");
            }
        },

        addRow: function(row) {
            this.table.addData([row]);
            return this;
        },

        clear: function() {
            this.table.clearData();
            return this;
        },

        getData: function() {

            return this.table.getData();
        },

        getBtnContainer: function() {
            return this.btnContainer;
        },

        getSmallBtnContainer: function() {
            return this.smallBtnContainer;
        },

        appendToFieldsArea: function(el) {
            this.smallBtnContainer.append(el);
            return this;
        },

        on: function(event, handler) {
            this.callbacks[event] = handler;
            return this;
        },

        off: function(event) {
            delete this.callbacks[event];
            return this;
        }

    }

}
