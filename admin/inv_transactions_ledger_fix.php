    async function saveTransaction(e) {
        e.preventDefault();
        const btn = document.getElementById('saveBtn');
        btn.disabled = true;
        btn.textContent = 'Recording...';

        const formData = new FormData(document.getElementById('txForm'));
        try {
            const base = window.location.pathname.replace(/\/[^/]*$/, '/');
            const apiUrl = base + 'inventory_transactions_api.php';
            const res = await fetch(apiUrl, { method: 'POST', body: formData });
            const rawText = await res.text();
            let data;
            try {
                data = JSON.parse(rawText);
            } catch (_) {
                console.error('API response:', rawText);
                alert('Invalid response from server. Check console for details.');
                return;
            }
            if (data.success) {
                closeModal();
                fetchUpdatedTable(1);
                
                if (data.fifo_deductions && data.fifo_deductions.length > 0) {
                    let summary = 'FIFO Stock-Out Summary:\n\n';
                    data.fifo_deductions.forEach(d => {
                        summary += `Roll: ${d.roll_code}\n`;
                        summary += `  Deducted: ${parseFloat(d.deducted).toFixed(2)} ft\n`;
                        summary += `  Was: ${parseFloat(d.was).toFixed(2)} ft → Now: ${parseFloat(d.now).toFixed(2)} ft`;
                        if (d.status === 'FINISHED') summary += ' (FINISHED)';
                        summary += '\n\n';
                    });
                    alert(summary);
                }
            } else {
                const errMsg = data.error || (data.errors && typeof data.errors === 'object' ? Object.values(data.errors).join(' ') : 'Unknown error');
                alert('Error: ' + errMsg);
            }
        } catch (err) {
            console.error('Network error:', err);
            alert('Network failure. Check that the server is running and the API URL is correct.');
        } 
        finally { btn.disabled = false; btn.textContent = 'Submit Entry'; }
    }
