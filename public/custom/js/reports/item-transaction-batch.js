$(function() {
	"use strict";

    let originalButtonText;

    const tableId = $('#batchReport');

    let taxSelectionBox = $('select[name="customer_id"]')

    /**
     * Language
     * */
    const _lang = {
                total : "Total",
                noRecordsFound : "No Records Found!!",
            };

    $("#reportForm").on("submit", function(e) {
        e.preventDefault();
        const form = $(this);
        const formArray = {
            formId: form.attr("id"),
            csrf: form.find('input[name="_token"]').val(),
            url: form.closest('form').attr('action'),
            formObject : form,
        };
        ajaxRequest(formArray);
    });

    function disableSubmitButton(form) {
        originalButtonText = form.find('button[type="submit"]').text();
        form.find('button[type="submit"]')
            .prop('disabled', true)
            .html('  <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>Loading...');
    }

    function enableSubmitButton(form) {
        form.find('button[type="submit"]')
            .prop('disabled', false)
            .html(originalButtonText);
    }

    function beforeCallAjaxRequest(formObject){
        disableSubmitButton(formObject);
        showSpinner();
    }
    function afterCallAjaxResponse(formObject){
        enableSubmitButton(formObject);
        hideSpinner();
    }
    function afterSeccessOfAjaxRequest(formObject, response){
        formAdjustIfSaveOperation(response);
    }
    function afterFailOfAjaxRequest(formObject){
        showNoRecordsMessageOnTableBody();
    }

    function ajaxRequest(formArray){
        var formData = new FormData(document.getElementById(formArray.formId));
        var jqxhr = $.ajax({
            type: 'POST',
            url: formArray.url,
            data: formData,
            dataType: 'json',
            contentType: false,
            processData: false,
            headers: {
                'X-CSRF-TOKEN': formArray.csrf
            },
            beforeSend: function() {
                // Actions to be performed before sending the AJAX request
                if (typeof beforeCallAjaxRequest === 'function') {
                    beforeCallAjaxRequest(formArray.formObject);
                }
            },
        });
        jqxhr.done(function(response) {
            // Actions to be performed after response from the AJAX request
            if (typeof afterSeccessOfAjaxRequest === 'function') {
                afterSeccessOfAjaxRequest(formArray.formObject, response);
            }
        });
        jqxhr.fail(function(response) {
            var message = response.responseJSON.message;
            iziToast.error({title: 'Error', layout: 2, message: message});
            if (typeof afterFailOfAjaxRequest === 'function') {
                afterFailOfAjaxRequest(formArray.formObject);
            }
        });
        jqxhr.always(function() {
            // Actions to be performed after the AJAX request is completed, regardless of success or failure
            if (typeof afterCallAjaxResponse === 'function') {
                afterCallAjaxResponse(formArray.formObject);
            }
        });
    }

    function formAdjustIfSaveOperation(response){
        var tableBody = tableId.find('tbody');

        var id = 1;
        var tr = "";

        var totalQuantity = parseFloat(0);
        var totalStockImpact = parseFloat(0);

        $.each(response.data, function(index, item) {
            totalQuantity += parseFloat(item.quantity);
            totalStockImpact += parseFloat(item.stock_impact);

             tr += "<tr>"
             tr += "<td>" + id++ + "</td>";
             tr += "<td>" + item.transaction_date + "</td>";
             tr += "<td>" + item.transaction_type + "</td>";
             tr += "<td>" + item.invoice_or_bill_code + "</td>";
             tr += "<td>" + item.party_name + "</td>";
             tr += "<td>" + item.warehouse + "</td>";
             tr += "<td>" + item.item_name + "</td>";
             tr += "<td>" + item.brand_name + "</td>";
             tr += "<td>" + item.batch_no + "</td>";
             tr += `<td class="${(!itemSettings.enable_mfg_date)?'d-none':''}">${item.mfg_date}</td>"`;
             tr += `<td class="${(!itemSettings.enable_exp_date)?'d-none':''}">${item.exp_date}</td>"`;
             tr += `<td class="${(!itemSettings.enable_model)?'d-none':''}">${item.model_no}</td>"`;
             tr += `<td class="${(!itemSettings.enable_color)?'d-none':''}">${item.color}</td>"`;
             tr += `<td class="${(!itemSettings.enable_size)?'d-none':''}">${item.size}</td>"`;
             tr += "<td class='text-end'>" + _parseQuantity(item.quantity) + "</td>";
             tr += `<td class='text-end text-${item.stock_impact_color}'>` + _parseQuantity(item.stock_impact) + "</td>";
             tr += "</tr>"
        });

        tr += "<tr class='fw-bold'>";
        tr += `<td colspan='0' class='text-end tfoot-first-td'>${_lang.total}</td>"`;
        tr += "<td class='text-end'>" + _parseQuantity(totalStockImpact) + "</td>";
        tr += "</tr>";

        // Clear existing rows:
        tableBody.empty();
        tableBody.append(tr);

        /**
         * Set colspan of the table bottom
         * */
        $('.tfoot-first-td').attr('colspan', columnCountWithoutDNoneClass(1));
    }

    function showNoRecordsMessageOnTableBody() {
        var tableBody = tableId.find('tbody');

        var tr = "<tr class='fw-bold'>";
        tr += `<td colspan='0' class='text-end tfoot-first-td text-center'>${_lang.noRecordsFound}</td>"`;
        tr += "</tr>";

        tableBody.empty();
        tableBody.append(tr);

        /**
         * Set colspan of the table bottom
         * */
        $('.tfoot-first-td').attr('colspan', columnCountWithoutDNoneClass(0));
    }
    function columnCountWithoutDNoneClass(minusCount) {
        return tableId.find('thead > tr:first > th').not('.d-none').length - minusCount;
    }

    /**
     *
     * Table Exporter
     * PDF, SpreadSheet
     * */
    $(document).on("click", '#generate_pdf', function() {
        tableId.tableExport({type:'pdf',escape:'false', fileName: 'Batch-Item-Transaction-Report'});
    });

    $(document).on("click", '#generate_excel', function() {
        tableId.tableExport({type:'xlsx',escape:'false', fileName: 'Batch-Item-Transaction-Report'});
    });

});//main function
