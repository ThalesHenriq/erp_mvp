<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$filtro_status = $_GET['filtro_status'] ?? 1; // Filtro padrão: ativos

// Carregar dados para os selects (apenas ativos)
try {
    $clientes = $pdo->query("SELECT id, nome FROM clientes WHERE status = 1 ORDER BY nome ASC")->fetchAll();
    $produtos = $pdo->query("SELECT id, nome, estoque FROM produtos WHERE status = 1 ORDER BY nome ASC")->fetchAll();
} catch (PDOException $e) {
    error_log("Erro ao buscar dados: " . $e->getMessage());
    redirectWithMessage('pedidos.php', 'erro', 'Erro ao carregar dados.');
}

if ($action === 'delete' && $id) {
    try {
        $pdo->beginTransaction();

        // 1. Recuperar dados do pedido
        $stmt = $pdo->prepare("SELECT produto_id, quantidade FROM pedidos WHERE id = ? AND status = 1");
        $stmt->execute([$id]);
        $pedido = $stmt->fetch();

        if (!$pedido) {
            throw new Exception("Pedido não encontrado ou já cancelado.");
        }

        // 2. Devolver ao estoque
        $stmt = $pdo->prepare("UPDATE produtos SET estoque = estoque + ? WHERE id = ?");
        $stmt->execute([$pedido['quantidade'], $pedido['produto_id']]);

        // 3. Cancelar pedido (exclusão lógica)
        $stmt = $pdo->prepare("UPDATE pedidos SET status = 0 WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();
        redirectWithMessage('pedidos.php', 'sucesso', 'Pedido cancelado e estoque devolvido!');
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erro ao cancelar pedido: " . $e->getMessage());
        redirectWithMessage('pedidos.php', 'erro', $e->getMessage());
    }
}
// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $cliente_id = filter_input(INPUT_POST, 'cliente_id', FILTER_SANITIZE_NUMBER_INT);
    $produto_id = filter_input(INPUT_POST, 'produto_id', FILTER_SANITIZE_NUMBER_INT);
    $quantidade = filter_input(INPUT_POST, 'quantidade', FILTER_SANITIZE_NUMBER_INT);
    $status = isset($_POST['ativo']) ? 1 : 0;

    // Validações
    if ($cliente_id <= 0 || $produto_id <= 0 || $quantidade <= 0) {
        redirectWithMessage('pedidos.php', 'erro', 'Preencha todos os campos corretamente!');
    }

    try {
        $pdo->beginTransaction();

        if ($id) {
            // Edição - devolver estoque do pedido antigo
            $stmt = $pdo->prepare("SELECT produto_id, quantidade FROM pedidos WHERE id = ?");
            $stmt->execute([$id]);
            $pedidoAntigo = $stmt->fetch();
            
            if ($pedidoAntigo) {
                $stmt = $pdo->prepare("UPDATE produtos SET estoque = estoque + ? WHERE id = ?");
                $stmt->execute([$pedidoAntigo['quantidade'], $pedidoAntigo['produto_id']]);
            }
        }

        // Verificar estoque
        $stmt = $pdo->prepare("SELECT estoque FROM produtos WHERE id = ? FOR UPDATE");
        $stmt->execute([$produto_id]);
        $produto = $stmt->fetch();

        if (!$produto) {
            throw new Exception("Produto não encontrado.");
        }

        if ($produto['estoque'] < $quantidade) {
            throw new Exception("Estoque insuficiente. Disponível: " . $produto['estoque']);
        }

        // Salvar pedido
        if ($id) {
            $stmt = $pdo->prepare("UPDATE pedidos SET cliente_id = ?, produto_id = ?, quantidade = ?, status = ? WHERE id = ?");
            $stmt->execute([$cliente_id, $produto_id, $quantidade, $status, $id]);
            $mensagem = 'Pedido atualizado com sucesso!';
        } else {
            $stmt = $pdo->prepare("INSERT INTO pedidos (cliente_id, produto_id, quantidade, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$cliente_id, $produto_id, $quantidade, $status]);
            $mensagem = 'Pedido registrado com sucesso!';
        }

        // Atualizar estoque
        $stmt = $pdo->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ?");
        $stmt->execute([$quantidade, $produto_id]);

        $pdo->commit();
        redirectWithMessage('pedidos.php', 'sucesso', $mensagem);
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erro no pedido: " . $e->getMessage());
        redirectWithMessage('pedidos.php', 'erro', $e->getMessage());
    }
}

// Cancelar pedido (exclusão lógica)
if ($action === 'cancelar' && $id) {
    try {
        $pdo->beginTransaction();

        // 1. Devolver estoque
        $stmt = $pdo->prepare("SELECT produto_id, quantidade FROM pedidos WHERE id = ?");
        $stmt->execute([$id]);
        $pedido = $stmt->fetch();

        if ($pedido) {
            $stmt = $pdo->prepare("UPDATE produtos SET estoque = estoque + ? WHERE id = ?");
            $stmt->execute([$pedido['quantidade'], $pedido['produto_id']]);
        }

        // 2. Marcar como cancelado
        $stmt = $pdo->prepare("UPDATE pedidos SET status = 0 WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();
        redirectWithMessage('pedidos.php', 'sucesso', 'Pedido cancelado com sucesso!');
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erro ao cancelar pedido: " . $e->getMessage());
        redirectWithMessage('pedidos.php', 'erro', 'Erro ao cancelar pedido.');
    }
}

// Carregar pedido para edição
$pedidoEdicao = null;
if ($action === 'edit' && $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?");
        $stmt->execute([$id]);
        $pedidoEdicao = $stmt->fetch();
        
        if (!$pedidoEdicao) {
            redirectWithMessage('pedidos.php', 'erro', 'Pedido não encontrado.');
        }
    } catch (PDOException $e) {
        error_log("Erro ao carregar pedido: " . $e->getMessage());
        redirectWithMessage('pedidos.php', 'erro', 'Erro ao carregar dados do pedido.');
    }
}

// Listar pedidos
try {
    $sql = "
        SELECT p.id, c.nome AS cliente, pr.nome AS produto, p.quantidade, 
               p.data_pedido, p.status 
        FROM pedidos p
        JOIN clientes c ON p.cliente_id = c.id
        JOIN produtos pr ON p.produto_id = pr.id
        WHERE 1=1
    ";
    
    $params = [];
    if ($filtro_status >= 0) {
        $sql .= " AND p.status = ?";
        $params[] = $filtro_status;
    }
    
    $sql .= " ORDER BY p.data_pedido DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erro ao buscar pedidos: " . $e->getMessage());
    redirectWithMessage('pedidos.php', 'erro', 'Erro ao carregar pedidos.');
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Pedidos</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <a href="index.php" 
        class="btn btn-editar">VOLTAR</a>
        
    <div class="container">
        <h1>Gestão de Pedidos</h1>
        
        <?php displayFlashMessages(); ?>

     <div class="filtro-container">
            <form method="GET" class="filtro-form">
                <label for="filtro_status">Status:</label>
                <select name="filtro_status" id="filtro_status" onchange="this.form.submit()">
                    <option value="1" <?= $filtro_status == 1 ? 'selected' : '' ?>>Ativos</option>
                    <option value="0" <?= $filtro_status == 0 ? 'selected' : '' ?>>Inativos</option>
                    <option value="-1" <?= $filtro_status == -1 ? 'selected' : '' ?>>Todos</option>
                </select>
            </form>
        </div>
        <form method="POST">
            <input type="hidden" name="id" value="<?= $pedidoEdicao['id'] ?? '' ?>">
            
            <div class="form-group">
                <label for="cliente_id">Cliente:</label>
                <select id="cliente_id" name="cliente_id" required>
                    <option value="">Selecione um cliente</option>
                    <?php foreach ($clientes as $cliente): ?>
                        <option value="<?= $cliente['id'] ?>"
                            <?= ($pedidoEdicao['cliente_id'] ?? '') == $cliente['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cliente['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="produto_id">Produto:</label>
                <select id="produto_id" name="produto_id" required>
                    <option value="">Selecione um produto</option>
                    <?php foreach ($produtos as $produto): ?>
                        <option value="<?= $produto['id'] ?>"
                            data-estoque="<?= $produto['estoque'] ?>"
                            <?= ($pedidoEdicao['produto_id'] ?? '') == $produto['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($produto['nome']) ?> 
                            (Estoque: <?= $produto['estoque'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="quantidade">Quantidade:</label>
                <input type="number" id="quantidade" name="quantidade" min="1" 
                       value="<?= htmlspecialchars($pedidoEdicao['quantidade'] ?? '1') ?>" required>
                <span id="estoque-disponivel" class="estoque-info"></span>
            </div>

            <div class="form-group form-check">
                <input type="checkbox" class="form-check-input" id="ativo" name="ativo" value="1"
                       <?= ($produtoEdicao['status'] ?? 1) ? 'checked' : '' ?>>
                <label class="form-check-label" for="ativo">Pedido ativo</label>
            </div>

            <button type="submit" class="btn">
                <?= $pedidoEdicao ? 'Atualizar' : 'Registrar' ?> Pedido
            </button>
            
            <?php if ($pedidoEdicao): ?>
                <a href="pedidos.php" class="btn btn-cancelar">Cancelar</a>
            <?php endif; ?>
        </form>
        
        <h2>Pedidos Registrados</h2>
        
        <?php if (empty($pedidos)): ?>
            <p>Nenhum pedido registrado.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Cliente</th>
                        <th>Produto</th>
                        <th>Quantidade</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedidos as $pedido): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($pedido['data_pedido'])) ?></td>
                            <td><?= htmlspecialchars($pedido['cliente']) ?></td>
                            <td><?= htmlspecialchars($pedido['produto']) ?></td>
                            <td><?= htmlspecialchars($pedido['quantidade']) ?></td>
                            <td class="actions">
                                <a href="pedidos.php?action=edit&id=<?= $pedido['id'] ?>" 
                                   class="btn btn-editar">Editar</a>
                                <a href="pedidos.php?action=delete&id=<?= $pedido['id'] ?>" 
                                   class="btn btn-excluir"
                                   onclick="return confirm('Tem certeza que deseja excluir este pedido?')">Excluir</a>
                            </td>
                            <td>
                                <span class="status-badge <?= $pedido['status'] ? 'ativo' : 'inativo' ?>">
                                    <?= $pedido['status'] ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <script>
        // Atualizar informação de estoque quando selecionar produto
        document.getElementById('produto_id').addEventListener('change', function() {
            const produtoSelect = this;
            const quantidadeInput = document.getElementById('quantidade');
            const estoqueInfo = document.getElementById('estoque-disponivel');
            
            if (produtoSelect.selectedOptions[0].value) {
                const estoque = produtoSelect.selectedOptions[0].dataset.estoque;
                estoqueInfo.textContent = `Estoque disponível: ${estoque}`;
                quantidadeInput.max = estoque;
            } else {
                estoqueInfo.textContent = '';
                quantidadeInput.removeAttribute('max');
            }
        });

        // Disparar o evento change ao carregar a página (para edição)
        document.getElementById('produto_id').dispatchEvent(new Event('change'));
    </script>
</body>
</html>