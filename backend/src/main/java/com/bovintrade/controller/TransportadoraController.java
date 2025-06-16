package com.bovintrade.controller;

import com.bovintrade.model.Transportadora;
import com.bovintrade.repository.TransportadoraRepository;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.util.Optional;

@RestController
@RequestMapping("/transportadoras")
@CrossOrigin(origins = "*")
public class TransportadoraController {

    @Autowired
    private TransportadoraRepository transportadoraRepo;

    @GetMapping("/{id}")
    public ResponseEntity<Transportadora> getById(@PathVariable Long id) {
        Optional<Transportadora> t = transportadoraRepo.findById(id);
        return t.map(ResponseEntity::ok)
                .orElse(ResponseEntity.notFound().build());
    }

    @PutMapping("/{id}")
    public ResponseEntity<String> atualizar(@PathVariable Long id, @RequestBody Transportadora dados) {
        Optional<Transportadora> t = transportadoraRepo.findById(id);
        if (t.isEmpty()) return ResponseEntity.notFound().build();

        Transportadora atual = t.get();
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
        atual.setCnhMotorista(dados.getCnhMotorista());
        atual.setPlacaVeiculo(dados.getPlacaVeiculo());
        atual.setTipoVeiculo(dados.getTipoVeiculo());
        atual.setCapacidadeTransporte(dados.getCapacidadeTransporte());

        transportadoraRepo.save(atual);
        return ResponseEntity.ok("Atualizado com sucesso!");
    }

    @DeleteMapping("/{id}")
    public ResponseEntity<String> deletar(@PathVariable Long id) {
        if (transportadoraRepo.existsById(id)) {
            transportadoraRepo.deleteById(id);
            return ResponseEntity.ok("Transportadora excluída com sucesso!");
        }
        return ResponseEntity.notFound().build();
    }
}
