document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const codeCards = document.querySelectorAll('.code-card');
    const exportButton = document.getElementById('exportButton');

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchQuery = searchInput.value.trim().toLowerCase();
            codeCards.forEach(function(card) {
                const codeText = card.querySelector('.card-text').textContent.trim().toLowerCase();
                if (codeText.includes(searchQuery)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }

    if (exportButton) {
        exportButton.addEventListener('click', function() {
            const txtContent = generateText(pwcpData.generated_codes);
            const txtBlob = new Blob([txtContent], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(txtBlob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'generated_codes.txt';
            document.body.appendChild(link);
            link.click();
            URL.revokeObjectURL(url);
            document.body.removeChild(link);
        });
    }

    function generateText(data) {
        let txtContent = 'Code\tStatus\n';
        data.forEach(function(row) {
            txtContent += row.code + '\t' + row.status + '\n';
        });
        return txtContent;
    }
});
