

;; Implement the `ft-trait` trait defined in the `ft-trait` contract

(impl-trait 'SP3FBR2AGK5H9QBDH3EEN6DF8EK8JY7RX8QJ5SVTE.sip-010-trait-ft-standard.sip-010-trait)

(define-fungible-token asdads u8)

(define-constant ERR_ADMIN_ONLY (err u401))
(define-constant ERR_NOT_TOKEN_OWNER (err u403))

(define-constant CONTRACT_OWNER 'SP39DTEJFPPWA3295HEE5NXYGMM7GJ8MA0TQX379)
(define-constant TOKEN_URI u"") ;; utf-8 string with token metadata host
(define-constant TOKEN_NAME "asdads")
(define-constant TOKEN_SYMBOL "ASD")
(define-constant TOKEN_DECIMALS u3) ;; 6 units displayed past decimal, e.g. 1.000_000 = 1 token

;; Transfers tokens to a recipient
(define-public (transfer
  (amount uint)
  (sender principal)
  (recipient principal)
  (memo (optional (buff 34)))
)
  (if (is-eq tx-sender sender)
    (begin
      (try! (ft-transfer? asdads amount sender recipient))
      (print memo)
      (ok true)
    )
    (err ERR_NOT_TOKEN_OWNER)))

(define-public (set-token-uri (token_uri (option buff 34)))
  (if (is-eq tx-sender (ft-get-owner asdads))
    (begin
      (ft-set-token-uri asdads token_uri)
      (ok true))
    (err ERR_ADMIN_ONLY)))





(ft-mint? asdads u8 CONTRACT_OWNER)
