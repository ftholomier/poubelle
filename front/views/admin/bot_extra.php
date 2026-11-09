<div class="card">
  <h2>Tester l'assistant</h2>
  <form class="bo-form" data-api="bot.test">
    <div class="f"><label class="f-label" for="bt-q">Question</label><input id="bt-q" name="q" value="Quels produits proposez-vous ?"></div>
    <button class="b b--l" type="submit">Envoyer la question</button>
  </form>
  <details style="margin-top:18px"><summary>Voir les consignes envoyées à l'IA (construites à partir du contenu du site)</summary><pre class="pre"><?= e(Bot::systemPrompt()) ?></pre></details>
</div>
