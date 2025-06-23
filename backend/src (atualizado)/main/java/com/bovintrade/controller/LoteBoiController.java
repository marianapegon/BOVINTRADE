package com.bovintrade.controller;

import com.bovintrade.model.Fazenda;
import com.bovintrade.model.LoteBoi;
import com.bovintrade.repository.FazendaRepository;
import com.bovintrade.repository.LoteBoiRepository;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.util.List;
import java.util.Optional;

@RestController
@RequestMapping("/lotes")
@CrossOrigin(origins = "*")
public class LoteBoiController {

    @Autowired
    private LoteBoiRepository loteRepo;

    @Autowired
    private FazendaRepository fazendaRepo;

    @PostMapping
    public ResponseEntity<?> cadastrar(@RequestBody LoteBoi lote) {
        if (lote.getFazenda() == null || lote.getFazenda().getId() == 0) {
            return ResponseEntity.badRequest().body("Fazenda não informada.");
        }

        Optional<Fazenda> fazenda = fazendaRepo.findById(lote.getFazenda().getId());
        if (fazenda.isEmpty()) {
            return ResponseEntity.badRequest().body("Fazenda não encontrada.");
        }

        lote.setFazenda(fazenda.get());
        loteRepo.save(lote);
        return ResponseEntity.ok("Lote cadastrado com sucesso!");
    }

    @GetMapping
    public List<LoteBoi> listar() {
        return loteRepo.findAll();
    }

    @DeleteMapping("/{id}")
    public ResponseEntity<?> excluir(@PathVariable Integer id) {
        if (loteRepo.existsById(id)) {
            loteRepo.deleteById(id);
            return ResponseEntity.ok("Lote excluído com sucesso!");
        } else {
            return ResponseEntity.status(404).body("Lote não encontrado.");
        }
    }
}
