package com.bovintrade.controller;

import com.bovintrade.model.Fazenda;
import com.bovintrade.repository.FazendaRepository;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.util.Optional;

@RestController
@RequestMapping("/fazendas")
@CrossOrigin(origins = "*")
public class FazendaController {

    @Autowired
    private FazendaRepository fazendaRepo;

    @GetMapping("/{id}")
    public ResponseEntity<Fazenda> getById(@PathVariable Long id) {
        Optional<Fazenda> faz = fazendaRepo.findById(id);
        return faz.map(ResponseEntity::ok)
                .orElse(ResponseEntity.notFound().build());
    }

    @PutMapping("/{id}")
    public ResponseEntity<String> atualizar(@PathVariable Long id, @RequestBody Fazenda dados) {
        Optional<Fazenda> faz = fazendaRepo.findById(id);
        if (faz.isEmpty()) return ResponseEntity.notFound().build();

        Fazenda atual = faz.get();
        atual.setNome(dados.getNome());
        atual.setTelefone(dados.getTelefone());
        atual.setEmail(dados.getEmail());
        atual.setCidade(dados.getCidade());
        atual.setEstado(dados.getEstado());
        atual.setBairro(dados.getBairro());
        atual.setRua(dados.getRua());
        atual.setNumero(dados.getNumero());
        atual.setComplemento(dados.getComplemento());
        atual.setSistemaCriacao(dados.getSistemaCriacao());
        atual.setNomeResponsavel(dados.getNomeResponsavel());
        atual.setCargoResponsavel(dados.getCargoResponsavel());

        fazendaRepo.save(atual);
        return ResponseEntity.ok("Atualizado com sucesso!");
    }

    @DeleteMapping("/{id}")
    public ResponseEntity<String> deletar(@PathVariable Long id) {
        Optional<Fazenda> faz = fazendaRepo.findById(id);
        if (faz.isEmpty()) {
            return ResponseEntity.notFound().build();
        }

        fazendaRepo.deleteById(id);
        return ResponseEntity.ok("Fazenda excluída com sucesso!");
    }
}
