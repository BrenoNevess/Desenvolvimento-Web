<?php
session_start();

require_once __DIR__ . '/Control/ControlUsuario.php';

function escapar($valor) {
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function textoFormulario($valor) {
    return is_string($valor) ? trim($valor) : '';
}

$controlUsuario = new ControlUsuario();
$termo = textoFormulario($_GET['pesquisar'] ?? '');

if (empty($_SESSION['token_csrf'])) {
    $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token_csrf'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['token_csrf'], $token)) {
        $_SESSION['mensagem'] = 'Sua sessão expirou. Atualize a página e tente novamente.';
        $_SESSION['tipo_mensagem'] = 'erro';
    } else {
        $acao = $_POST['acao'] ?? '';
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);

        try {
            if ($acao === 'excluir') {
                if ($id === false) {
                    throw new InvalidArgumentException('O identificador do usuário é inválido.');
                }

                $excluido = $controlUsuario->excluirUsuario($id);
                $_SESSION['mensagem'] = $excluido
                    ? 'Usuário excluído com sucesso.'
                    : 'Usuário não encontrado para exclusão.';
                $_SESSION['tipo_mensagem'] = $excluido ? 'sucesso' : 'erro';
            } elseif ($acao === 'salvar') {
                $nome = textoFormulario($_POST['nome'] ?? '');
                $email = textoFormulario($_POST['email'] ?? '');

                if ($nome === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException('Informe um nome e um endereço de e-mail válido.');
                }

                if ($id === false) {
                    $salvo = $controlUsuario->cadastrarUsuario($nome, $email);
                    $_SESSION['mensagem'] = $salvo
                        ? 'Usuário cadastrado com sucesso.'
                        : 'Não foi possível cadastrar o usuário.';
                } else {
                    $salvo = $controlUsuario->editarUsuario($id, $nome, $email);
                    $_SESSION['mensagem'] = $salvo
                        ? 'Usuário atualizado com sucesso.'
                        : 'Usuário não encontrado para edição.';
                }
                $_SESSION['tipo_mensagem'] = $salvo ? 'sucesso' : 'erro';
            } else {
                throw new InvalidArgumentException('Ação inválida.');
            }
        } catch (InvalidArgumentException $e) {
            $_SESSION['mensagem'] = $e->getMessage();
            $_SESSION['tipo_mensagem'] = 'erro';
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $_SESSION['mensagem'] = 'Não foi possível salvar a alteração. Verifique os dados e tente novamente.';
            $_SESSION['tipo_mensagem'] = 'erro';
        } catch (RuntimeException $e) {
            error_log($e->getMessage());
            $_SESSION['mensagem'] = 'Não foi possível conectar ao banco de dados.';
            $_SESSION['tipo_mensagem'] = 'erro';
        }
    }

    $url = 'index.php';
    if ($termo !== '') {
        $url .= '?pesquisar=' . rawurlencode($termo);
    }
    header('Location: ' . $url);
    exit;
}

$mensagem = $_SESSION['mensagem'] ?? null;
$tipoMensagem = $_SESSION['tipo_mensagem'] ?? 'sucesso';
unset($_SESSION['mensagem'], $_SESSION['tipo_mensagem']);

try {
    $usuarios = $controlUsuario->listarUsuarios($termo);
    $erroListagem = null;
} catch (PDOException $e) {
    error_log($e->getMessage());
    $usuarios = [];
    $erroListagem = 'Não foi possível carregar os usuários. Verifique a conexão e a estrutura do banco de dados.';
} catch (RuntimeException $e) {
    error_log($e->getMessage());
    $usuarios = [];
    $erroListagem = 'Não foi possível conectar ao banco de dados.';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de usuários</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 32px 16px;
            background: #f3f5f8;
            color: #202733;
            font-family: Arial, sans-serif;
        }
        main {
            max-width: 1000px;
            margin: 0 auto;
            padding: 28px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, .08);
        }
        h1, h2 { margin-top: 0; }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            align-items: end;
        }
        label { display: block; margin-bottom: 6px; font-weight: 600; }
        input {
            width: 100%;
            min-height: 40px;
            padding: 9px 11px;
            border: 1px solid #c9d0da;
            border-radius: 6px;
            font: inherit;
        }
        button, .button {
            display: inline-block;
            min-height: 38px;
            padding: 9px 14px;
            border: 0;
            border-radius: 6px;
            background: #2457a7;
            color: #fff;
            font: inherit;
            text-decoration: none;
            cursor: pointer;
        }
        button:hover, .button:hover { filter: brightness(.92); }
        .secondary { background: #596575; }
        .danger { background: #b42332; }
        .edit { background: #a96800; }
        .actions { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .actions form { margin: 0; }
        .notice { margin: 18px 0; padding: 13px 16px; border-radius: 7px; }
        .notice.sucesso { background: #dff3e5; color: #175c2e; }
        .notice.erro { background: #fbe2e3; color: #751e24; }
        .search { margin: 28px 0 18px; }
        .search-row { display: flex; gap: 8px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 11px; border: 1px solid #d9dee6; text-align: left; }
        th { background: #eef1f5; }
        .empty { color: #596575; text-align: center; }
        @media (max-width: 600px) {
            main { padding: 20px 14px; }
            .search-row { flex-direction: column; }
        }
    </style>
</head>
<body>
<main>
    <h1>Gerenciamento de usuários</h1>

    <?php if ($mensagem !== null): ?>
        <div class="notice <?= escapar($tipoMensagem) ?>" role="status"><?= escapar($mensagem) ?></div>
    <?php endif; ?>

    <?php if ($erroListagem !== null): ?>
        <div class="notice erro" role="alert"><?= escapar($erroListagem) ?></div>
    <?php endif; ?>

    <section aria-labelledby="form-title">
        <h2 id="form-title">Cadastrar usuário</h2>
        <form method="post" action="index.php" id="usuario-form">
            <input type="hidden" name="token_csrf" value="<?= escapar($_SESSION['token_csrf']) ?>">
            <input type="hidden" name="acao" value="salvar">
            <input type="hidden" name="id" id="usuario-id">
            <div class="form-grid">
                <div>
                    <label for="nome">Nome</label>
                    <input type="text" name="nome" id="nome" maxlength="150" required>
                </div>
                <div>
                    <label for="email">E-mail</label>
                    <input type="email" name="email" id="email" maxlength="254" required>
                </div>
                <div class="actions">
                    <button type="submit" id="salvar">Cadastrar</button>
                    <button type="button" class="secondary" id="cancelar" hidden>Cancelar edição</button>
                </div>
            </div>
        </form>
    </section>

    <form method="get" action="index.php" class="search">
        <label for="pesquisar">Pesquisar por nome ou e-mail</label>
        <div class="search-row">
            <input type="search" name="pesquisar" id="pesquisar" value="<?= escapar($termo) ?>">
            <button type="submit">Pesquisar</button>
            <?php if ($termo !== ''): ?>
                <a class="button secondary" href="index.php">Limpar</a>
            <?php endif; ?>
        </div>
    </form>

    <h2>Usuários cadastrados</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$usuarios): ?>
                    <tr><td class="empty" colspan="4">Nenhum usuário encontrado.</td></tr>
                <?php else: ?>
                    <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td><?= escapar($usuario['id']) ?></td>
                            <td><?= escapar($usuario['nome']) ?></td>
                            <td><?= escapar($usuario['email']) ?></td>
                            <td>
                                <div class="actions">
                                    <button
                                        type="button"
                                        class="edit editar"
                                        data-id="<?= escapar($usuario['id']) ?>"
                                        data-nome="<?= escapar($usuario['nome']) ?>"
                                        data-email="<?= escapar($usuario['email']) ?>"
                                    >Editar</button>
                                    <form method="post" action="index.php" onsubmit="return confirm('Tem certeza que deseja excluir este usuário?');">
                                        <input type="hidden" name="token_csrf" value="<?= escapar($_SESSION['token_csrf']) ?>">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= escapar($usuario['id']) ?>">
                                        <button type="submit" class="danger">Excluir</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
<script>
    const form = document.getElementById('usuario-form');
    const idInput = document.getElementById('usuario-id');
    const nomeInput = document.getElementById('nome');
    const emailInput = document.getElementById('email');
    const salvarButton = document.getElementById('salvar');
    const cancelarButton = document.getElementById('cancelar');

    document.querySelectorAll('.editar').forEach((button) => {
        button.addEventListener('click', () => {
            idInput.value = button.dataset.id;
            nomeInput.value = button.dataset.nome;
            emailInput.value = button.dataset.email;
            salvarButton.textContent = 'Salvar alterações';
            cancelarButton.hidden = false;
            document.getElementById('form-title').textContent = 'Editar usuário';
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            nomeInput.focus();
        });
    });

    cancelarButton.addEventListener('click', () => {
        form.reset();
        idInput.value = '';
        salvarButton.textContent = 'Cadastrar';
        cancelarButton.hidden = true;
        document.getElementById('form-title').textContent = 'Cadastrar usuário';
    });
</script>
</body>
</html>
