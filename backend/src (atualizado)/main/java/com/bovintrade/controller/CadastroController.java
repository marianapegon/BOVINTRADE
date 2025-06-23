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

@RestController
@RequestMapping("/cadastro")
@CrossOrigin(origins = "*")
public class CadastroController {

    @Autowired
    private FazendaRepository fazendaRepo;

    @Autowired
    private FrigorificoRepository frigorificoRepo;

    @Autowired
    private TransportadoraRepository transportadoraRepo;

    @PostMapping
    public ResponseEntity<String> cadastrar(@RequestBody CadastroDTO dto) {
        try {
            String tipo = dto.getTipo();

            if (tipo.equalsIgnoreCase("FAZENDA")) {
                Fazenda f = new Fazenda();
                f.setNome(dto.getNome());
                f.setCnpj(dto.getCnpj());
                f.setEmail(dto.getEmail());
                f.setTelefone(dto.getTelefone());
                f.setSenha(dto.getSenha());
                f.setSistemaCriacao(dto.getSistemaCriacao());
                f.setCep(dto.getCep());
                f.setCidade(dto.getCidade());
                f.setEstado(dto.getEstado());
                f.setBairro(dto.getBairro());
                f.setRua(dto.getRua());
                f.setNumero(dto.getNumero());
                f.setComplemento(dto.getComplemento());
                f.setNomeResponsavel(dto.getNomeResponsavel());
                f.setCpfResponsavel(dto.getCpfResponsavel());
                f.setCargoResponsavel(dto.getCargoResponsavel());
                f.setLatitude(dto.getLatitude());
                f.setLongitude(dto.getLongitude());
                f.setTipo("FAZENDA");
                fazendaRepo.save(f);
                return ResponseEntity.ok("Fazenda cadastrada com sucesso!");

            } else if (tipo.equalsIgnoreCase("FRIGORIFICO")) {
                Frigorifico fr = new Frigorifico();
                fr.setNome(dto.getNome());
                fr.setCnpj(dto.getCnpj());
                fr.setEmail(dto.getEmail());
                fr.setTelefone(dto.getTelefone());
                fr.setSenha(dto.getSenha());
                fr.setCep(dto.getCep());
                fr.setCidade(dto.getCidade());
                fr.setEstado(dto.getEstado());
                fr.setBairro(dto.getBairro());
                fr.setRua(dto.getRua());
                fr.setNumero(dto.getNumero());
                fr.setComplemento(dto.getComplemento());
                fr.setNomeResponsavel(dto.getNomeResponsavel());
                fr.setCpfResponsavel(dto.getCpfResponsavel());
                fr.setCargoResponsavel(dto.getCargoResponsavel());
                fr.setLatitude(dto.getLatitudeFrig());
                fr.setLongitude(dto.getLongitudeFrig());
                fr.setTipo("FRIGORIFICO");
                frigorificoRepo.save(fr);
                return ResponseEntity.ok("Frigorífico cadastrado com sucesso!");

            } else if (tipo.equalsIgnoreCase("TRANSPORTADORA")) {
                Transportadora t = new Transportadora();
                t.setNome(dto.getNome());
                t.setCnpj(dto.getCnpj());
                t.setEmail(dto.getEmail());
                t.setTelefone(dto.getTelefone());
                t.setSenha(dto.getSenha());
                t.setCep(dto.getCep());
                t.setCidade(dto.getCidade());
                t.setEstado(dto.getEstado());
                t.setBairro(dto.getBairro());
                t.setRua(dto.getRua());
                t.setNumero(dto.getNumero());
                t.setComplemento(dto.getComplemento());
                t.setNomeResponsavel(dto.getNomeResponsavel());
                t.setCpfResponsavel(dto.getCpfResponsavel());
                t.setCargoResponsavel(dto.getCargoResponsavel());
                t.setCnhMotorista(dto.getCnhMotorista());
                t.setPlacaVeiculo(dto.getPlacaVeiculo());
                t.setTipoVeiculo(dto.getTipoVeiculo());
                t.setCapacidadeTransporte(dto.getCapacidadeTransporte() != null ? Integer.parseInt(dto.getCapacidadeTransporte()) : 0);
                t.setTipo("TRANSPORTADORA");
                transportadoraRepo.save(t);
                return ResponseEntity.ok("Transportadora cadastrada com sucesso!");
            }

            return ResponseEntity.badRequest().body("Tipo de entidade inválido.");
        } catch (Exception e) {
            e.printStackTrace();
            return ResponseEntity.status(500).body("Erro no cadastro: " + e.getMessage());
        }
    }
}
