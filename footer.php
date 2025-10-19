</main>
      
      <?php if(isset($_SESSION['user_id'])): // Exibe o footer apenas para usuários logados ?>
        <footer class="main-footer-container">
            <p>&copy; <?php echo date("Y"); ?> <?php echo htmlspecialchars($_SESSION['nome_empresa']); ?>. Todos os direitos reservados.</p>
        </footer>
      <?php endif; ?>

    </div> <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>