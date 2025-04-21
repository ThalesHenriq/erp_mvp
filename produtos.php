<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$filtro_status = $_GET['filtro_status'] ?? 1; // Filtro padrão: mostrar apenas ativos

// Listar produtos com filtro de status
try {
    $sql = "SELECT * FROM produtos WHERE 1=1";
    $params = [];
    
    if ($filtro_status >= 0) { // -1 mostra todos
        $sql .= " AND status = ?";
        $params[] = $filtro_status;
    }
    
    $sql .= " ORDER BY nome ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $produtos = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erro ao buscar produtos: " . $e->getMessage());
    redirectWithMessage('produtos.php', 'erro', 'Erro ao carregar produtos.');
}

if ($action === 'delete' && $id) {
    try {
        // Verificar se o produto está em pedidos ativos
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE produto_id = ? AND status = 1");
        $stmt->execute([$id]);
        $temPedidos = $stmt->fetchColumn();

        if ($temPedidos > 0) {
            redirectWithMessage('produtos.php', 'erro', 'Este produto está em pedidos ativos e não pode ser inativado.');
        }

        // Exclusão lógica
        $stmt = $pdo->prepare("UPDATE produtos SET status = 0 WHERE id = ?");
        $stmt->execute([$id]);
        
        redirectWithMessage('produtos.php', 'sucesso', 'Produto inativado com sucesso!');
    } catch (PDOException $e) {
        error_log("Erro ao inativar produto: " . $e->getMessage());
        redirectWithMessage('produtos.php', 'erro', 'Erro ao inativar produto.');
    }
}
// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $nome = sanitizeInput($_POST['nome'] ?? '');
    $preco = filter_input(INPUT_POST, 'preco', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $estoque = filter_input(INPUT_POST, 'estoque', FILTER_SANITIZE_NUMBER_INT);
    $status = isset($_POST['ativo']) ? 1 : 0;

    // Validações
    if (empty($nome) || $preco <= 0 || $estoque < 0) {
        redirectWithMessage('produtos.php', 'erro', 'Preencha todos os campos corretamente!');
    }

    try {
        // Verificar se produto já existe (exceto para o próprio produto em edição)
        $stmt = $pdo->prepare("SELECT id FROM produtos WHERE nome = ? AND id != ?");
        $stmt->execute([$nome, $id]);
        
        if ($stmt->fetch()) {
            redirectWithMessage('produtos.php', 'erro', 'Já existe um produto com este nome.');
        }

        if ($id) {
            // Atualizar produto existente
            $stmt = $pdo->prepare("UPDATE produtos SET nome = ?, preco = ?, estoque = ?, status = ? WHERE id = ?");
            $stmt->execute([$nome, $preco, $estoque, $status, $id]);
            $mensagem = 'Produto atualizado com sucesso!';
        } else {
            // Inserir novo produto
            $stmt = $pdo->prepare("INSERT INTO produtos (nome, preco, estoque, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nome, $preco, $estoque, $status]);
            $mensagem = 'Produto cadastrado com sucesso!';
        }
        
        redirectWithMessage('produtos.php', 'sucesso', $mensagem);
    } catch (PDOException $e) {
        error_log("Erro ao salvar produto: " . $e->getMessage());
        redirectWithMessage('produtos.php', 'erro', 'Erro ao salvar produto.');
    }
}

// Carregar produto para edição
$produtoEdicao = null;
if ($action === 'edit' && $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
        $stmt->execute([$id]);
        $produtoEdicao = $stmt->fetch();
        
        if (!$produtoEdicao) {
            redirectWithMessage('produtos.php', 'erro', 'Produto não encontrado.');
        }
    } catch (PDOException $e) {
        error_log("Erro ao carregar produto: " . $e->getMessage());
        redirectWithMessage('produtos.php', 'erro', 'Erro ao carregar dados do produto.');
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Produtos</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
     <a href="index.php" 
        class="btn btn-editar">VOLTAR</a>
        
    <div class="container">
        <h1>Gestão de Produtos</h1>
        
        <?php displayFlashMessages(); ?>
        
        <!-- Filtro de Status -->
       <!-- <div class="filtro-container">
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
            <input type="hidden" name="id" value="<?= $produtoEdicao['id'] ?? '' ?>">
            
            <div class="form-group">
                <label for="nome">Nome:</label>
                <input type="text" id="nome" name="nome" 
                       value="<?= htmlspecialchars($produtoEdicao['nome'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="preco">Preço (R$):</label>
                <input type="number" id="preco" name="preco" step="0.01" min="0.01"
                       value="<?= htmlspecialchars($produtoEdicao['preco'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="estoque">Estoque:</label>
                <input type="number" id="estoque" name="estoque" min="0"
                       value="<?= htmlspecialchars($produtoEdicao['estoque'] ?? '') ?>" required>
            </div>
            
            <div class="form-group form-check">
                <input type="checkbox" class="form-check-input" id="ativo" name="ativo" value="1"
                       <?= ($produtoEdicao['status'] ?? 1) ? 'checked' : '' ?>>
                <label class="form-check-label" for="ativo">Produto ativo</label>
            </div>
            
            <button type="submit" class="btn">
                <?= $produtoEdicao ? 'Atualizar' : 'Cadastrar' ?> Produto
            </button>
            
            <?php if ($produtoEdicao): ?>
                <a href="produtos.php" class="btn btn-cancelar">Cancelar</a>
            <?php endif; ?>
        </form>
        
        <h2>Produtos Cadastrados</h2>
        
        <?php if (empty($produtos)): ?>
            <p>Nenhum produto encontrado.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Preço</th>
                        <th>Estoque</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produtos as $produto): ?>
                        <tr>
                            <td><?= htmlspecialchars($produto['nome']) ?></td>
                            <td>R$ <?= number_format($produto['preco'], 2, ',', '.') ?></td>
                            <td><?= htmlspecialchars($produto['estoque']) ?></td>
                            <td>
                                <span class="status-badge <?= $produto['status'] ? 'ativo' : 'inativo' ?>">
                                    <?= $produto['status'] ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </td>
                            <td class="actions">
                                <a href="produtos.php?action=edit&id=<?= $produto['id'] ?>" 
                                   class="btn btn-editar">Editar</a>
                                <a href="clientes.php?action=delete&id=<?= $produto['id'] ?>" 
                                   class="btn btn-excluir"
                                   onclick="return confirm('Tem certeza que deseja excluir este cliente?')">Excluir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>