package com.bovintrade.DTO;

public class CadastroDTO {
    private String tipo;

    // Comuns
    private String nome;
    private String cnpj;
    private String email;
    private String telefone;
    private String senha;


    // Endereço
    private String cep;
    private String cidade;
    private String estado;
    private String bairro;
    private String rua;
    private String numero;
    private String complemento;

    // Responsável
    private String nomeResponsavel;
    private String cpfResponsavel;
    private String cargoResponsavel;

    // Fazenda
    private String sistemaCriacao;

    // Frigorífico
    private Double latitude;
    private Double longitude;
    private Double latitudeFrig;
    private Double longitudeFrig;


    // Transportadora
    private String cnhMotorista;
    private String placaVeiculo;
    private String tipoVeiculo;
    private String capacidadeTransporte;

    // Getters e Setters

    public String getTipo() {
        return tipo;
    }

    public void setTipo(String tipo) {
        this.tipo = tipo;
    }

    public String getNome() {
        return nome;
    }

    public void setNome(String nome) {
        this.nome = nome;
    }

    public String getCnpj() {
        return cnpj;
    }

    public void setCnpj(String cnpj) {
        this.cnpj = cnpj;
    }

    public String getEmail() {
        return email;
    }

    public void setEmail(String email) {
        this.email = email;
    }

    public String getTelefone() {
        return telefone;
    }

    public void setTelefone(String telefone) {
        this.telefone = telefone;
    }
    public String getSenha() {
        return senha;
    }

    public void setSenha(String senha) {
        this.senha = senha;
    }

    public String getCep() {
        return cep;
    }

    public void setCep(String cep) {
        this.cep = cep;
    }

    public String getCidade() {
        return cidade;
    }

    public void setCidade(String cidade) {
        this.cidade = cidade;
    }

    public String getEstado() {
        return estado;
    }

    public void setEstado(String estado) {
        this.estado = estado;
    }

    public String getBairro() {
        return bairro;
    }

    public void setBairro(String bairro) {
        this.bairro = bairro;
    }

    public String getRua() {
        return rua;
    }

    public void setRua(String rua) {
        this.rua = rua;
    }

    public String getNumero() {
        return numero;
    }

    public void setNumero(String numero) {
        this.numero = numero;
    }

    public String getComplemento() {
        return complemento;
    }

    public void setComplemento(String complemento) {
        this.complemento = complemento;
    }

    public String getNomeResponsavel() {
        return nomeResponsavel;
    }

    public void setNomeResponsavel(String nomeResponsavel) {
        this.nomeResponsavel = nomeResponsavel;
    }

    public String getCpfResponsavel() {
        return cpfResponsavel;
    }

    public void setCpfResponsavel(String cpfResponsavel) {
        this.cpfResponsavel = cpfResponsavel;
    }

    public String getCargoResponsavel() {
        return cargoResponsavel;
    }

    public void setCargoResponsavel(String cargoResponsavel) {
        this.cargoResponsavel = cargoResponsavel;
    }

    public String getSistemaCriacao() {
        return sistemaCriacao;
    }

    public void setSistemaCriacao(String sistemaCriacao) {
        this.sistemaCriacao = sistemaCriacao;
    }

    public Double getLatitude() {
        return latitude;
    }

    public void setLatitude(Double latitude) {
        this.latitude = latitude;
    }

    public Double getLongitude() {
        return longitude;
    }

    public void setLongitude(Double longitude) {
        this.longitude = longitude;
    }

    public Double getLatitudeFrig() {
        return latitudeFrig;
    }

    public void setLatitudeFrig(Double latitudeFrig) {
        this.latitudeFrig = latitudeFrig;
    }

    public Double getLongitudeFrig() {
        return longitudeFrig;
    }

    public void setLongitudeFrig(Double longitudeFrig) {
        this.longitudeFrig = longitudeFrig;
    }


    public String getCnhMotorista() {
        return cnhMotorista;
    }

    public void setCnhMotorista(String cnhMotorista) {
        this.cnhMotorista = cnhMotorista;
    }

    public String getPlacaVeiculo() {
        return placaVeiculo;
    }

    public void setPlacaVeiculo(String placaVeiculo) {
        this.placaVeiculo = placaVeiculo;
    }

    public String getTipoVeiculo() {
        return tipoVeiculo;
    }

    public void setTipoVeiculo(String tipoVeiculo) {
        this.tipoVeiculo = tipoVeiculo;
    }

    public String getCapacidadeTransporte() {
        return capacidadeTransporte;
    }

    public void setCapacidadeTransporte(String capacidadeTransporte) {
        this.capacidadeTransporte = capacidadeTransporte;
    }
}
