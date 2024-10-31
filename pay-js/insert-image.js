jQuery(document).ready(function($) {
    function insertImageBesidePayWithCode() {
        var payWithCodeLabel = $('label[for="payment_method_pwcp"]');

        if (payWithCodeLabel.length > 0) {
            payWithCodeLabel.find('div').remove();

            payWithCodeLabel.append(`
                <div style="display: flex; align-items: center; justify-content: flex-start; margin: 5px 20px;">
                    <img src="${pwcp_image_url}" alt="Pay With Code Image" style="max-width: 300px; height: auto; margin-left: -30px;">
                </div>
            `);
        }
    }

    insertImageBesidePayWithCode();

    $(document).ajaxComplete(function() {
        insertImageBesidePayWithCode();
    });
});
