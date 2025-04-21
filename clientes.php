<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$filtro_status = $_GET['filtro_status'] ?? 1; // Filtro padrão: ativos

// Listar clientes com filtro
try {
    $sql = "SELECT * FROM clientes WHERE 1=1";
    $params = [];
    
    if ($filtro_status >= 0) {
        $sql .= " AND status = ?";
        $params[] = $filtro_status;
    }
    
    $sql .= " ORDER BY nome ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $clientes = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erro ao buscar clientes: " . $e->getMessage());
    redirectWithMessage('clientes.php', 'erro', 'Erro ao carregar clientes.');
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $nome = sanitizeInput($_POST['nome'] ?? '');
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $telefone = sanitizeInput($_POST['telefone'] ?? '');
    $status = isset($_POST['ativo']) ? 1 : 0;

    // Validações (como antes)
    if (empty($nome) || empty($email)) {
        redirectWithMessage('clientes.php', 'erro', 'Nome e e-mail são obrigatórios!');
    }

    try {
        if ($id) {
            // Atualizar cliente
            $stmt = $pdo->prepare("UPDATE clientes SET nome = ?, email = ?, telefone = ?, status = ? WHERE id = ?");
            $stmt->execute([$nome, $email, $telefone, $status, $id]);
            $mensagem = 'Cliente atualizado com sucesso!';
        } else {
            // Inserir novo cliente
            $stmt = $pdo->prepare("INSERT INTO clientes (nome, email, telefone, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nome, $email, $telefone, $status]);
            $mensagem = 'Cliente cadastrado com sucesso!';
        }
        
        redirectWithMessage('clientes.php', 'sucesso', $mensagem);
    } catch (PDOException $e) {
        error_log("Erro ao salvar cliente: " . $e->getMessage());
        redirectWithMessage('clientes.php', 'erro', 'Erro ao salvar cliente.');
    }
}

if ($action === 'delete' && $id) {
    try {
        // Verificar se o cliente tem pedidos ativos
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE cliente_id = ? AND status = 1");
        $stmt->execute([$id]);
        $temPedidos = $stmt->fetchColumn();

        if ($temPedidos > 0) {
            redirectWithMessage('clientes.php', 'erro', 'Este cliente possui pedidos ativos e não pode ser inativado.');
        }

        // Exclusão lógica
        $stmt = $pdo->prepare("UPDATE clientes SET status = 0 WHERE id = ?");
        $stmt->execute([$id]);
        
        redirectWithMessage('clientes.php', 'sucesso', 'Cliente inativado com sucesso!');
    } catch (PDOException $e) {
        error_log("Erro ao inativar cliente: " . $e->getMessage());
        redirectWithMessage('clientes.php', 'erro', 'Erro ao inativar cliente.');
    }
}
// Carregar cliente para edição
$clienteEdicao = null;
if ($action === 'edit' && $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
        $stmt->execute([$id]);
        $clienteEdicao = $stmt->fetch();
        
        if (!$clienteEdicao) {
            redirectWithMessage('clientes.php', 'erro', 'Cliente não encontrado.');
        }
    } catch (PDOException $e) {
        error_log("Erro ao carregar cliente: " . $e->getMessage());
        redirectWithMessage('clientes.php', 'erro', 'Erro ao carregar dados do cliente.');
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Clientes</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <a href="index.php" 
       class="btn btn-editar">VOLTAR</a>
    <div class="container">
        <h1>Gestão de Clientes</h1>
        
        <?php displayFlashMessages(); ?>

        <!--<div class="filtro-container">
            <form method="GET" class="filtro-form">
                <label for="filtro_status">Status:</label>
                <select name="filtro_status" id="filtro_status" onchange="this.form.submit()">
                    <option value="1" <?= $filtro_status == 1 ? 'selected' : '' ?>>Ativos</option>
                    <option value="0" <?= $filtro_status == 0 ? 'selected' : '' ?>>Inativos</option>
                    <option value="-1" <?= $filtro_status == -1 ? 'selected' : '' ?>>Todos</option>
                </select>
            </form>
        </div>-->
        
        <form method="POST">
            <input type="hidden" name="id" value="<?= $clienteEdicao['id'] ?? '' ?>">
            
            <div class="form-group">
                <label for="nome">Nome:</label>
                <input type="text" id="nome" name="nome" 
                       value="<?= htmlspecialchars($clienteEdicao['nome'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">E-mail:</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($clienteEdicao['email'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="telefone">Telefone:</label>
                <input type="text" id="telefone" name="telefone"
                       value="<?= htmlspecialchars($clienteEdicao['telefone'] ?? '') ?>">
            </div>

            <div class="form-group form-check">
                <input type="checkbox" class="form-check-input" id="ativo" name="ativo" value="1"
                       <?= ($produtoEdicao['status'] ?? 1) ? 'checked' : '' ?>>
                <label class="form-check-label" for="ativo">Cliente ativo</label>
            </div>

            <button type="submit" class="btn">
                <?= $clienteEdicao ? 'Atualizar' : 'Cadastrar' ?> Cliente
            </button>
            
            <?php if ($clienteEdicao): ?>
                <a href="clientes.php" class="btn btn-cancelar">Cancelar</a>
            <?php endif; ?>
        </form>
        
        <h2>Clientes Cadastrados</h2>
        
        <?php if (empty($clientes)): ?>
            <p>Nenhum cliente cadastrado.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Telefone</th>
                        <th>Ações</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clientes as $cliente): ?>
                        <tr>
                            <td><?= htmlspecialchars($cliente['nome']) ?></td>
                            <td><?= htmlspecialchars($cliente['email']) ?></td>
                            <td><?= htmlspecialchars($cliente['telefone']) ?></td>
                            <td class="actions">
                                <a href="clientes.php?action=edit&id=<?= $cliente['id'] ?>" 
                                   class="btn btn-editar">Editar</a>
                                <a href="clientes.php?action=delete&id=<?= $cliente['id'] ?>" 
                                   class="btn btn-excluir"
                                   onclick="return confirm('Tem certeza que deseja excluir este cliente?')">Excluir</a>
                            </td>
                            <td>
                                <span class="status-badge <?= $cliente['status'] ? 'ativo' : 'inativo' ?>">
                                    <?= $cliente['status'] ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>