document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('dash-range');

    const picker = new Litepicker({
        element: input,
        singleMode: false,
        numberOfMonths: 2,
        numberOfColumns: 2,
        format: 'YYYY-MM-DD',
        autoApply: true,
        showTooltip: true,

        setup: (picker) => {
            picker.on('selected', (start, end) => {
                const startStr = start.format('YYYY-MM-DD');
                const endStr = end.format('YYYY-MM-DD');

                // Hidden inputs for backend
                document.getElementById('dash-start-hidden').value = startStr;
                document.getElementById('dash-end-hidden').value = endStr;

                // Visible input (UX)
                input.value = `${startStr} → ${endStr}`;
            });
        }
    });

    // Pre-fill if coming from server
    /*const start = "<?= $_GET['start'] ?>";
    const end = "<?= $_GET['end'] ?>";

    if (start && end) {
        input.value = `${start} → ${end}`;
    }
        */
});