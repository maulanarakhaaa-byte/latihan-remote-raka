<?php

?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'NihonGo!') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Noto+Serif+JP:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/base.css">
<?php if (!empty($pageCss)): ?>
<link rel="stylesheet" href="../assets/css/<?= htmlspecialchars($pageCss) ?>.css">
<?php endif; ?>
<?php if (!empty($accentColor)): ?>
<style>:root { --accent: <?= htmlspecialchars($accentColor) ?>; }</style>
<?php endif; ?>
</head>
<body>
