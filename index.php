<?php include 'includes/db.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ERP MVP</title>
    <link rel="stylesheet" href="styles.css">
    <!-- Você pode incluir uma fonte externa, por exemplo do Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet">
</head>
<body>
    <header>
        <h1>ERP MVP</h1>
        <nav aria-label="Navegação principal">
            <ul class="menu">
                <li><a href="clientes.php">Clientes</a></li>
                <li><a href="produtos.php">Produtos</a></li>
                <li><a href="pedidos.php">Pedidos</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <section>
            <p>Bem-vindo ao sistema ERP MVP. Utilize o menu acima para acessar os módulos.</p>
        </section>
    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> ERP MVP. Todos os direitos reservados.</p>
    </footer>
</body>
</html>
