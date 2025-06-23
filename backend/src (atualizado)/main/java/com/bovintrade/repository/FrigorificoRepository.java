package com.bovintrade.repository;

import com.bovintrade.model.Frigorifico;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.stereotype.Repository;

@Repository
public interface FrigorificoRepository extends JpaRepository<Frigorifico, Long> {
    Frigorifico findByEmailAndSenha(String email, String senha);
}
