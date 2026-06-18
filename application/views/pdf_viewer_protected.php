<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Consultation sécurisée du document PDF</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        .toolbar {
            background-color: #323639;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
            z-index: 1000;
        }
        .toolbar button {
            background-color: #4a4d51;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
        }
        .toolbar button:hover {
            background-color: #5a5d61;
        }
        #pdf-container {
            flex: 1;
            overflow: auto;
            background-color: #f8f9fa;
            padding: 20px;
        }
        canvas {
            display: block;
            margin: 10px auto;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }
        .page-number {
            color: #6c757d;
            margin: 10px 0;
            font-size: 14px;
            text-align: center;
        }
        .user-info {
            background-color: #e9ecef;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
        }
        .user-info h5 {
            margin-bottom: 5px;
            color: #212529;
        }
        .alert {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 5px;
        }
        .alert-info {
            background-color: #d1ecf1;
            border-color: #bee5eb;
        }
        .alert-warning {
            background-color: #fff3cd;
            border-color: #ffeeba;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="user-info">
            <h5>Consultation en cours</h5>
            <p>Utilisateur: <?= html_escape($this->session->userdata('username')) ?></p>
            <p>IP: <?= $_SERVER['REMOTE_ADDR'] ?? 'Non disponible' ?></p>
            <p>Date: <?= date('d/m/Y H:i:s') ?></p>
        </div>

        <div class="alert alert-info">
            <strong>Attention:</strong> Ce document est protégé. Toute tentative de téléchargement ou d'impression sera tracée.
        </div>

        <div class="toolbar">
            <h3>🔒 Consultation sécurisée</h3>
            <button onclick="window.close()">Fermer</button>
        </div>

        <div id="pdf-container">
            <p>Chargement du document...</p>
        </div>

        <div class="alert alert-warning">
            <strong>Remarque:</strong> Les actions de consultation sont enregistrées dans le journal des activités.
        </div>
    </div>

    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        const url = <?= json_encode($pdf_url) ?>;
        const container = document.getElementById('pdf-container');

        // Bloquer les actions courantes
        document.addEventListener('contextmenu', e => { e.preventDefault(); return false; });
        document.addEventListener('keydown', e => {
            if (e.ctrlKey && (e.key === 's' || e.key === 'S' || e.key === 'p' || e.key === 'P' || e.key === 'u' || e.key === 'U')) {
                e.preventDefault();
                alert("L'impression et le téléchargement ne sont pas autorisés.");
                return false;
            }
            if (e.key === 'F12') {
                e.preventDefault();
                alert("L'outil de développement est désactivé.");
                return false;
            }
            if (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'C' || e.key === 'J')) {
                e.preventDefault();
                return false;
            }
        });


        // Charger le PDF
        pdfjsLib.getDocument(url).promise.then(pdf => {
            container.innerHTML = '';
            const pageCount = pdf.numPages;
            let pageNum = 1;

            // Rendre chaque page
            for (let i = 1; i <= pageCount; i++) {
                pdf.getPage(i).then(page => {
                    const scale = 1.2;
                    const viewport = page.getViewport({ scale: scale });
                    const canvas = document.createElement('canvas');
                    canvas.width = viewport.width;
                    canvas.height = viewport.height;
                    const pageDiv = document.createElement('div');
                    const pageLabel = document.createElement('p');
                    pageLabel.className = 'page-number';
                    pageLabel.textContent = 'Page ' + i + ' / ' + pageCount;
                    pageDiv.appendChild(canvas);
                    pageDiv.appendChild(pageLabel);
                    container.appendChild(pageDiv);

                    const ctx = canvas.getContext('2d');
                    page.render({ canvasContext: ctx, viewport: viewport });
                }).catch(error => {
                    console.error('Erreur lors du rendu de la page:', error);
                });
            }


        }).catch(error => {
            container.innerHTML = '<p style="color:red; padding:20px;">Erreur de chargement: ' + error + '</p>';
            console.error('Erreur de chargement du PDF:', error);
        });
    </script>
</body>
</html>