package com.bovintrade.repository;

import com.bovintrade.model.LoteBoi;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.stereotype.Repository;

import java.util.List;

@Repository
public interface LoteBoiRepository extends JpaRepository<LoteBoi, Integer> {
    List<LoteBoi> findByFazendaId(Long fazendaId);  // Busca todos os lotes de uma fazenda
}
