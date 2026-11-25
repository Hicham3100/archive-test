<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><style>
@page { margin: 8mm; } body { font-family: DejaVu Sans, sans-serif; } .grid { width:100%; display:flex; flex-wrap:wrap; } .label { box-sizing:border-box; width:48%; height:43mm; margin:1%; border:1px solid #333; border-radius:2mm; padding:3mm; display:inline-block; } .qr { width:30mm; height:30mm; float:left; margin-right:3mm; } .title { font-size:11pt; font-weight:bold; } .cote { font-size:14pt; font-weight:bold; margin-top:2mm; } .location { font-size:8pt; margin-top:2mm; }
</style></head><body><div class="grid">
<?php foreach($boxes as $box): ?>
<div class="label"><img class="qr" src="<?=html_escape($box['_qr'])?>"><div class="title"><?=html_escape($box['title'])?></div><div class="cote"><?=html_escape($box['cote'])?></div><div class="location"><?=html_escape($box['_location'])?></div></div>
<?php endforeach; ?>
</div></body></html>
