<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Statistiques des archives</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1 { text-align: center; }
        h2 { margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background-color: #3498db; color: white; }
        .total { font-weight: bold; background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Statistiques des archives</h1>
    <p>Généré le <?= date('d/m/Y H:i:s') ?></p>
    
    <h2>Nombre total d'archives : <?= $total_archives ?></h2>
    
    <h2>Répartition par structure</h2>
    <table>
        <thead><tr><th>Structure</th><th>Nombre</th></tr></thead>
        <tbody>
            <?php foreach ($stats_structure as $s): ?>
            <tr>
                <td><?= htmlspecialchars($s['structure_dam'] ?? 'Non défini') ?></td>
                <td><?= $s['total'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Top 5 des utilisateurs actifs</h2>
    <table>
        <thead><tr><th>Utilisateur</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach ($top_users as $u): ?>
            <tr><td><?= htmlspecialchars($u['username']) ?></td><td><?= $u['total_actions'] ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="text-align: center; margin-top: 40px; font-size: 10px;">Archives - Rapport confidentiel</div>
</body>
</html>