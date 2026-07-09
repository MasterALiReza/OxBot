#!/bin/bash
wg genkey | tee privatekey | wg pubkey > publickey
PRIV=PUB=ip link add dev wg99 type wireguard
ip addr add 10.99.99.1/24 dev wg99
wg set wg99 private-key privatekey
ip link set up dev wg99
wg set wg99 peer \ allowed-ips ''
wg-quick save wg99
cat /etc/wireguard/wg99.conf
ip link del dev wg99
