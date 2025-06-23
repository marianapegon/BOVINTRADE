package com.bovintrade.model;

import jakarta.persistence.*;
import java.time.LocalDate;

@Entity
public class Transporte {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private int id;

    @ManyToOne
    private LoteBoi lote;

    @ManyToOne
    private Usuario transportadora;

    private LocalDate dataPrevista;

    private String status = "AGENDADO";

    public Transporte() {}

    // Getters e Setters
    public int getId() { return id; }

    public LoteBoi getLote() { return lote; }
    public void setLote(LoteBoi lote) { this.lote = lote; }

    public Usuario getTransportadora() { return transportadora; }
    public void setTransportadora(Usuario transportadora) { this.transportadora = transportadora; }

    public LocalDate getDataPrevista() { return dataPrevista; }
    public void setDataPrevista(LocalDate dataPrevista) { this.dataPrevista = dataPrevista; }

    public String getStatus() { return status; }
    public void setStatus(String status) { this.status = status; }
}
