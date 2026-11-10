<?php
require_once __DIR__ . '/../Model/DAO/ClassUsuarioDAO.php';
require_once __DIR__ . '/../Model/ClassUsuario.php';

class ControlUsuario {
    public function cadastrarUsuario($nome, $email) {
        $usuario = new ClassUsuario();
        $usuario->setNome($nome);
        $usuario->setEmail($email);

        $usuarioDAO = new ClassUsuarioDAO();
        return $usuarioDAO->cadastrar($usuario);
    }

    public function buscarUsuario($id) {
        $usuarioDAO = new ClassUsuarioDAO();
        return $usuarioDAO->buscarUsuario($id);
    }

    public function listarUsuarios($termo = '') {
        $usuarioDAO = new ClassUsuarioDAO();
        return $usuarioDAO->listarUsuarios($termo);
    }

    public function editarUsuario($id, $nome, $email) {
        $usuario = new ClassUsuario();
        $usuario->setId($id);
        $usuario->setNome($nome);
        $usuario->setEmail($email);

        $usuarioDAO = new ClassUsuarioDAO();
        return $usuarioDAO->editarUsuario($usuario);
    }

    public function excluirUsuario($id) {
        $usuarioDAO = new ClassUsuarioDAO();
        return $usuarioDAO->deletarUsuario($id);
    }
}