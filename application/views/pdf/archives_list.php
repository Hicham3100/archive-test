<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Liste des archives</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1 { text-align: center; color: #2c3e50; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
        .footer { text-align: center; margin-top: 30px; font-size: 10px; color: #777; }
    </style>
</head>
<body>
    <h1><?= $title ?? 'Liste des archives' ?></h1>
    <p>Date d'export : <?= date('d/m/Y H:i:s') ?></p>
    <table>
        <thead>
            <tr>
                <th>Référence</th>
                <th>Titre</th>
                <th>Émetteur</th>
                <th>Destinataire</th>
                <th>Date création</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($archives as $a): ?>
            <tr>
                <td><?= htmlspecialchars($a['reference']) ?></td>
                <td><?= htmlspecialchars($a['titre']) ?></td>
                <td><?= htmlspecialchars($a['emetteur']) ?></td>
                <td><?= htmlspecialchars($a['destinataire']) ?></td>
                <td><?= date('d/m/Y', strtotime($a['date_creation'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="footer">Rapport généré depuis l'application Archives</div>
</body>
</html>