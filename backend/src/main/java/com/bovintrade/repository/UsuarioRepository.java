package com.bovintrade.repository;

import com.bovintrade.model.Usuario;
import org.springframework.data.jpa.repository.JpaRepository;

public interface UsuarioRepository extends JpaRepository<Usuario, Integer> {
    Usuario findByEmailAndSenha(String email, String senha);
}
