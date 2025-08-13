$(function() {
    "use strict";

    let originalButtonText;

    const tableId = $('#cashflowReport');

    /**
     * Language
     * */
    const _lang = {
                total : "Total",
                noRecordsFound : "No Records Found!!",
                balance : "Balance",
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

        var totalCashIn = parseFloat(0);
        var totalCashOut = parseFloat(0);
        var totalBalance = parseFloat(0);

        $.each(response.data, function(index, item) {
            totalCashIn += parseFloat(item.cash_in);
            totalCashOut += parseFloat(item.cash_out);
            totalBalance = totalCashIn - totalCashOut;

            tr  +=`
                <tr>
                    <td>${id++}</td>
                    <td>${item.transaction_date}</td>
                    <td>${item.invoice_or_bill_code}</td>
                    <td>${item.party_name}</td>
                    <td>${item.category_name}</td>
                    <td>${item.transaction_details}</td>
                    <td class='text-end ' data-tableexport-celltype="number" >${item.cash_in > 0 ? _formatNumber(item.cash_in) : ''}</td>
                    <td class='text-end ' data-tableexport-celltype="number" >${item.cash_out > 0 ? _formatNumber(item.cash_out) : ''}</td>
                    <td class='text-end text-${item.class_color}' data-tableexport-celltype="number" >${_formatNumber(totalBalance)}</td>
                </tr>
            `;
        });

        tr  +=`
            <tr class='fw-bold'>
                <td colspan='0' class='text-end tfoot-first-td'>${_lang.total}</td>
                <td class='text-end' data-tableexport-celltype="number">${_formatNumber(totalCashIn)}</td>
                <td class='text-end' data-tableexport-celltype="number">${_formatNumber(totalCashOut)}</td>
                <td class='text-end ' data-tableexport-celltype="number">${_formatNumber(totalBalance)}</td>
            </tr>
        `;


        // Clear existing rows:
        tableBody.empty();
        tableBody.append(tr);

        /**
         * Set colspan of the table bottom
         * */
        $('.tfoot-first-td').attr('colspan', columnCountWithoutDNoneClass(3));

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
        tableId.tableExport({type:'pdf',escape:'false', fileName: 'Cashflow-Report'});
    });

    $(document).on("click", '#generate_excel', function() {
        tableId.tableExport({
            formats: ["xlsx"],
            fileName: 'Cashflow-Report',
            xlsx: {
                onCellFormat: function (cell, e) {
                    if (typeof e.value === 'string') {
                        // Remove commas and convert to number
                        var numValue = parseFloat(e.value.replace(/,/g, ''));
                        if (!isNaN(numValue)) {
                            return numValue;
                        }
                    }
                    return e.value;
                }
            }
        });
    });

});//main function
