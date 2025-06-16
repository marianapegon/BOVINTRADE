package com.bovintrade.repository;

import com.bovintrade.model.Transportadora;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.stereotype.Repository;

@Repository
public interface TransportadoraRepository extends JpaRepository<Transportadora, Long> {
    Transportadora findByEmailAndSenha(String email, String senha);
}
