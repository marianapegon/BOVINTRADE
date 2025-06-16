package com.bovintrade.controller;

import com.bovintrade.DTO.CadastroDTO;
import com.bovintrade.model.Fazenda;
import com.bovintrade.model.Frigorifico;
import com.bovintrade.model.Transportadora;
import com.bovintrade.repository.FazendaRepository;
import com.bovintrade.repository.FrigorificoRepository;
import com.bovintrade.repository.TransportadoraRepository;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.util.HashMap;
import java.util.Map;

@RestController
@RequestMapping("/usuarios")
@CrossOrigin(origins = "*")
public class UsuarioController {

    @Autowired
    private FazendaRepository fazendaRepository;

    @Autowired
    private FrigorificoRepository frigorificoRepository;

    @Autowired
    private TransportadoraRepository transportadoraRepository;

    @PostMapping
    public ResponseEntity<?> login(@RequestBody Map<String, String> login) {
        String email = login.get("email");
        String senha = login.get("senha");

        Fazenda f = fazendaRepository.findByEmailAndSenha(email, senha);
        if (f != null) {
            Map<String, Object> resposta = new HashMap<>();
            resposta.put("tipo", "FAZENDA");
            resposta.put("nome", f.getNome());
            resposta.put("id", f.getId());
            return ResponseEntity.ok(resposta);
        }

        Frigorifico fr = frigorificoRepository.findByEmailAndSenha(email, senha);
        if (fr != null) {
            Map<String, Object> resposta = new HashMap<>();
            resposta.put("tipo", "FRIGORIFICO");
            resposta.put("nome", fr.getNome());
            resposta.put("id", fr.getId());
            return ResponseEntity.ok(resposta);
        }

        Transportadora t = transportadoraRepository.findByEmailAndSenha(email, senha);
        if (t != null) {
            Map<String, Object> resposta = new HashMap<>();
            resposta.put("tipo", "TRANSPORTADORA");
            resposta.put("nome", t.getNome());
            resposta.put("id", t.getId());
            return ResponseEntity.ok(resposta);
        }

        return ResponseEntity.status(401).body("Credenciais inválidas");
    }
}
