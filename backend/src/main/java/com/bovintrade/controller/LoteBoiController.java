package com.bovintrade.controller;

import com.bovintrade.model.LoteBoi;
import com.bovintrade.repository.LoteBoiRepository;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.web.bind.annotation.*;

import java.util.List;

@RestController
@RequestMapping("/lotes")
@CrossOrigin(origins = "*") // permite chamadas do frontend local
public class LoteBoiController {

    @Autowired
    private LoteBoiRepository loteRepo;

    @PostMapping
    public String cadastrar(@RequestBody LoteBoi lote) {
        loteRepo.save(lote);
        return "Lote cadastrado com sucesso!";
    }

    @GetMapping
    public List<LoteBoi> listar() {
        return loteRepo.findAll();
    }

    @DeleteMapping("/{id}")
    public String excluir(@PathVariable Integer id) {
        if (loteRepo.existsById(id)) {
            loteRepo.deleteById(id);
            return "Lote excluído com sucesso!";
        } else {
            return "Lote não encontrado!";
        }
    }
}
