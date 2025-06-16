package com.bovintrade.controller;

import com.bovintrade.model.Frigorifico;
import com.bovintrade.repository.FrigorificoRepository;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.util.Optional;

@RestController
@RequestMapping("/frigorificos")
@CrossOrigin(origins = "*")
public class FrigorificoController {

    @Autowired
    private FrigorificoRepository frigorificoRepo;

    // ✅ Cadastrar Frigorífico
    @PostMapping
    public ResponseEntity<?> cadastrar(@RequestBody Frigorifico frigorifico) {
        try {
            frigorifico.setTipo("FRIGORIFICO"); // Definir tipo no backend
            Frigorifico novo = frigorificoRepo.save(frigorifico);
            return ResponseEntity.ok(novo);
        } catch (Exception e) {
            return ResponseEntity.status(500).body("Erro ao cadastrar frigorífico.");
        }
    }

    // ✅ Buscar por ID
    @GetMapping("/{id}")
    public ResponseEntity<?> buscarPorId(@PathVariable Long id) {
        Optional<Frigorifico> fr = frigorificoRepo.findById(id);
        if (fr.isPresent()) {
            return ResponseEntity.ok(fr.get());
        } else {
            return ResponseEntity.status(404).body("Frigorífico não encontrado.");
        }
    }

    // ✅ Atualizar dados
    @PutMapping("/{id}")
    public ResponseEntity<?> atualizar(@PathVariable Long id, @RequestBody Frigorifico dados) {
        Optional<Frigorifico> fr = frigorificoRepo.findById(id);
        if (fr.isEmpty()) {
            return ResponseEntity.status(404).body("Frigorífico não encontrado.");
        }

        try {
            Frigorifico atual = fr.get();
            atual.setNome(dados.getNome());
            atual.setTelefone(dados.getTelefone());
            atual.setEmail(dados.getEmail());
            atual.setCidade(dados.getCidade());
            atual.setEstado(dados.getEstado());
            atual.setBairro(dados.getBairro());
            atual.setRua(dados.getRua());
            atual.setNumero(dados.getNumero());
            atual.setComplemento(dados.getComplemento());
            atual.setNomeResponsavel(dados.getNomeResponsavel());
            atual.setCargoResponsavel(dados.getCargoResponsavel());
            atual.setLatitude(dados.getLatitude());
            atual.setLongitude(dados.getLongitude());
            atual.setSenha(dados.getSenha()); // Permite alterar senha

            frigorificoRepo.save(atual);
            return ResponseEntity.ok("Frigorífico atualizado com sucesso.");
        } catch (Exception e) {
            return ResponseEntity.status(500).body("Erro ao atualizar frigorífico.");
        }
    }

    // ✅ Deletar frigorífico
    @DeleteMapping("/{id}")
    public ResponseEntity<?> deletar(@PathVariable Long id) {
        if (frigorificoRepo.existsById(id)) {
            frigorificoRepo.deleteById(id);
            return ResponseEntity.ok("Frigorífico excluído com sucesso.");
        }
        return ResponseEntity.status(404).body("Frigorífico não encontrado.");
    }
}
