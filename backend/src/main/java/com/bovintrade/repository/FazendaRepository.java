package com.bovintrade.repository;

import com.bovintrade.model.Fazenda;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.stereotype.Repository;

@Repository
public interface FazendaRepository extends JpaRepository<Fazenda, Long> {
    Fazenda findByEmailAndSenha(String email, String senha); // Para login
}
