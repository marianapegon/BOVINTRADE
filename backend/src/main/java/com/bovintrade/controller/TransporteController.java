package com.bovintrade.controller;

import com.bovintrade.model.LoteBoi;
import com.bovintrade.model.Transporte;
import com.bovintrade.model.Usuario;
import com.bovintrade.repository.LoteBoiRepository;
import com.bovintrade.repository.TransporteRepository;
import com.bovintrade.repository.UsuarioRepository;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.util.List;
import java.util.Optional;

@RestController
@RequestMapping("/transportes")
public class TransporteController {

    @Autowired
    private TransporteRepository transporteRepo;

    @Autowired
    private LoteBoiRepository loteRepo;

    @Autowired
    private UsuarioRepository usuarioRepo;

    @PostMapping
    public String agendar(@RequestBody Transporte t) {
        // valida se lote e transportadora existem
        Optional<LoteBoi> lote = loteRepo.findById(t.getLote().getId());
        Optional<Usuario> transp = usuarioRepo.findById(t.getTransportadora().getId());

        if (lote.isPresent() && transp.isPresent() && transp.get().getTipo().equals("TRANSPORTADORA")) {
            t.setLote(lote.get());
            t.setTransportadora(transp.get());
            transporteRepo.save(t);
            return "Transporte agendado com sucesso!";
        }

        return "Falha no agendamento. Dados inválidos.";
    }
    @PutMapping("/{id}/entregue")
    public ResponseEntity<String> marcarEntregue(@PathVariable int id) {
        Optional<Transporte> opt = transporteRepo.findById(id);
        if (opt.isPresent()) {
            Transporte t = opt.get();
            t.setStatus("ENTREGUE");
            transporteRepo.save(t);
            return ResponseEntity.ok("Status atualizado para ENTREGUE");
        } else {
            return ResponseEntity.notFound().build();
        }
    }

    @GetMapping
    public List<Transporte> listar() {
        return transporteRepo.findAll();
    }
}
