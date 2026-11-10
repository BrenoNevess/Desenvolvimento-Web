<?php
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/../ClassUsuario.php';

class ClassUsuarioDAO {
    public function cadastrar(ClassUsuario $usuario) {
        $pdo = Conexao::getInstance();
        $sql = 'INSERT INTO usuario (nome, email) VALUES (:nome, :email)';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':nome', $usuario->getNome());
        $stmt->bindValue(':email', $usuario->getEmail());
        return $stmt->execute();
    }

    public function buscarUsuario($id) {
        $pdo = Conexao::getInstance();
        $stmt = $pdo->prepare('SELECT id, nome, email FROM usuario WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $dados = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$dados) {
            return null;
        }

        $usuario = new ClassUsuario();
        $usuario->setId($dados['id']);
        $usuario->setNome($dados['nome']);
        $usuario->setEmail($dados['email']);
        return $usuario;
    }

    public function listarUsuarios($termo = '') {
        $pdo = Conexao::getInstance();
        $sql = 'SELECT id, nome, email FROM usuario';
        if ($termo !== '') {
            $sql .= ' WHERE nome LIKE :termo_nome OR email LIKE :termo_email';
        }
        $sql .= ' ORDER BY id DESC';

        $stmt = $pdo->prepare($sql);
        if ($termo !== '') {
            $stmt->bindValue(':termo_nome', '%' . $termo . '%');
            $stmt->bindValue(':termo_email', '%' . $termo . '%');
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function editarUsuario(ClassUsuario $usuario) {
        $pdo = Conexao::getInstance();
        $stmt = $pdo->prepare(
            'UPDATE usuario SET nome = :nome, email = :email WHERE id = :id'
        );
        $stmt->bindValue(':nome', $usuario->getNome());
        $stmt->bindValue(':email', $usuario->getEmail());
        $stmt->bindValue(':id', $usuario->getId(), PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            return true;
        }

        $verificar = $pdo->prepare('SELECT 1 FROM usuario WHERE id = :id LIMIT 1');
        $verificar->bindValue(':id', $usuario->getId(), PDO::PARAM_INT);
        $verificar->execute();
        return (bool) $verificar->fetchColumn();
    }

    public function deletarUsuario($id) {
        $pdo = Conexao::getInstance();
        $stmt = $pdo->prepare('DELETE FROM usuario WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
}