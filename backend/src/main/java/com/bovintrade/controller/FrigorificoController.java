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

    @GetMapping("/{id}")
    public ResponseEntity<?> getById(@PathVariable Long id) {
        Optional<Frigorifico> fr = frigorificoRepo.findById(id);
        if (fr.isPresent()) {
            return ResponseEntity.ok(fr.get());
        } else {
            return ResponseEntity.status(404).body("Frigorífico não encontrado");
        }
    }

    @PutMapping("/{id}")
    public ResponseEntity<?> atualizar(@PathVariable Long id, @RequestBody Frigorifico dados) {
        Optional<Frigorifico> fr = frigorificoRepo.findById(id);
        if (fr.isEmpty()) {
            return ResponseEntity.status(404).body("Frigorífico não encontrado");
        }

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

        frigorificoRepo.save(atual);
        return ResponseEntity.ok("Atualizado com sucesso!");
    }

    @DeleteMapping("/{id}")
    public ResponseEntity<?> deletar(@PathVariable Long id) {
        if (frigorificoRepo.existsById(id)) {
            frigorificoRepo.deleteById(id);
            return ResponseEntity.ok("Frigorífico excluído com sucesso!");
        }
        return ResponseEntity.status(404).body("Frigorífico não encontrado");
    }
}
